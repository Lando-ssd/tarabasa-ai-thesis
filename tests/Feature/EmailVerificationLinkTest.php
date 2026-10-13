<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * The link in the verification email must work for whoever opens it, wherever they open it. It used to need the
 * same person to be signed in as that account in the same browser, so a teacher who clicked it on a phone, in
 * another browser, or while an Admin was signed in on that computer stayed "Email not verified" and the Admin's
 * Approve button stayed locked.
 */
class EmailVerificationLinkTest extends TestCase
{
    use RefreshDatabase;

    private function unverifiedTeacher(string $email = 'tess@example.com'): User
    {
        $u = User::create(['first_name' => 'Tess', 'last_name' => 'Teacher', 'email' => $email, 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        Teacher::create(['user_id' => $u->id, 'school_name' => 'Rizal ES', 'employee_id' => 'EMP-1', 'status' => 'Pending', 'grades_handled' => ['Grade 1']]);

        return $u;
    }

    private function link(User $user, ?int $minutes = 60): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
            'id' => $user->getKey(),
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }

    public function test_the_link_verifies_the_account_when_nobody_is_signed_in(): void
    {
        $teacher = $this->unverifiedTeacher();

        $this->get($this->link($teacher))->assertOk()->assertSee('Email confirmed');

        $this->assertNotNull($teacher->fresh()->email_verified_at);
    }

    public function test_the_link_still_verifies_the_right_account_when_an_admin_is_signed_in(): void
    {
        $teacher = $this->unverifiedTeacher();
        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.com', 'password' => 'a-private-and-long-one-9', 'user_type' => 'Admin']);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)->get($this->link($teacher))->assertOk()->assertSee('Email confirmed');

        $this->assertNotNull($teacher->fresh()->email_verified_at);
    }

    public function test_it_still_works_when_the_teacher_is_signed_in_as_before(): void
    {
        $teacher = $this->unverifiedTeacher();

        $this->actingAs($teacher)->get($this->link($teacher))->assertOk();

        $this->assertNotNull($teacher->fresh()->email_verified_at);
    }

    public function test_opening_the_link_twice_is_harmless(): void
    {
        $teacher = $this->unverifiedTeacher();
        $url = $this->link($teacher);

        $this->get($url)->assertOk();
        $first = $teacher->fresh()->email_verified_at;
        $this->get($url)->assertOk();

        $this->assertEquals($first, $teacher->fresh()->email_verified_at);
    }

    public function test_a_link_cannot_verify_a_different_account(): void
    {
        $mine = $this->unverifiedTeacher('mine@example.com');
        $other = $this->unverifiedTeacher('other@example.com');

        // A properly signed link for one account, but the hash of someone else's email.
        $forged = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
            'id' => $mine->getKey(),
            'hash' => sha1($other->getEmailForVerification()),
        ]);

        $this->get($forged)->assertForbidden();

        $this->assertNull($mine->fresh()->email_verified_at);
        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_an_unsigned_or_tampered_link_is_refused(): void
    {
        $teacher = $this->unverifiedTeacher('mine@example.com');
        $other = $this->unverifiedTeacher('other@example.com');

        // No signature at all.
        $this->get(route('verification.verify', ['id' => $teacher->getKey(), 'hash' => sha1($teacher->email)]))->assertForbidden();

        // A genuine link whose account number was changed after it was signed.
        $tampered = str_replace('/email/verify/'.$teacher->getKey().'/', '/email/verify/'.$other->getKey().'/', $this->link($teacher));
        $this->get($tampered)->assertForbidden();

        $this->assertNull($teacher->fresh()->email_verified_at);
        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_an_expired_link_is_refused(): void
    {
        $teacher = $this->unverifiedTeacher();
        $url = $this->link($teacher, 60);

        $this->travel(61)->minutes();

        $this->get($url)->assertForbidden();
        $this->assertNull($teacher->fresh()->email_verified_at);
    }

    public function test_a_link_for_an_account_that_does_not_exist_is_refused(): void
    {
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => 999999, 'hash' => sha1('nobody@example.com')]);

        $this->get($url)->assertForbidden();
    }

    /**
     * The whole real path, as it happens on the host: the form is submitted through the host's HTTPS proxy, the email
     * carries a link, and that very link (not one made by the test) is opened later with nobody signed in.
     */
    public function test_the_link_in_the_real_registration_email_verifies_the_account_behind_the_hosts_proxy(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $proxy = ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'tarabasa-ai.onrender.com', 'X-Forwarded-Port' => '443'];

        $this->withHeaders($proxy)->post(route('register.teacher.submit'), [
            'first_name' => 'Pinky', 'last_name' => 'Reyes', 'email' => 'pinky@example.com', 'password' => 'Passw0rd!', 'password_confirmation' => 'Passw0rd!',
            'school_name' => 'Rizal ES', 'employee_id' => 'EMP-1', 'grades_mode' => 'single', 'grades_handled' => ['Grade 2'],
        ])->assertOk();

        $user = User::where('email', 'pinky@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);

        $url = null;
        \Illuminate\Support\Facades\Notification::assertSentTo($user, \Illuminate\Auth\Notifications\VerifyEmail::class, function ($n) use ($user, &$url) {
            $url = $n->toMail($user)->actionUrl;

            return true;
        });
        $this->assertStringStartsWith('https://tarabasa-ai.onrender.com/email/verify/', $url);

        // A new browser (nobody signed in), same proxy in front.
        auth()->logout();
        $this->flushSession();
        $this->withHeaders($proxy)->get($url)->assertOk()->assertSee('Email confirmed');

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    public function test_once_verified_the_admin_can_approve_the_teacher(): void
    {
        $teacher = $this->unverifiedTeacher();
        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.com', 'password' => 'a-private-and-long-one-9', 'user_type' => 'Admin']);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $profile = Teacher::where('user_id', $teacher->id)->first();

        // Before the link: the server refuses to approve (the button is locked).
        $this->actingAs($admin)->post(route('admin.teachers.activate', $profile))->assertSessionHasErrors('admin');
        $this->assertSame('Pending', $profile->fresh()->status);

        // The teacher opens the link on their own, with the Admin signed in on this same computer.
        $this->get($this->link($teacher));

        $this->actingAs($admin)->post(route('admin.teachers.activate', $profile));
        $this->assertSame('Active', $profile->fresh()->status);
    }
}

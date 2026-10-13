<?php

namespace Tests\Feature;

use App\Models\ParentAccount;
use App\Models\Teacher;
use App\Models\User;
use App\Support\EmailAddress;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Teachers and parents must open the link in their email before they can sign in (asked for on 2026-10-13 because
 * people typed addresses that were not real). The Admin, children and the switch that turns the rule off are covered
 * too, and an address that cannot be sent to leaves no account behind.
 */
class EmailConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['auth.require_verified_email' => true]);
    }

    private function teacherForm(array $o = []): array
    {
        return $o + [
            'first_name' => 'Pinky', 'last_name' => 'Reyes', 'email' => 'pinky@example.com', 'password' => 'Passw0rd!', 'password_confirmation' => 'Passw0rd!',
            'school_name' => 'Rizal ES', 'employee_id' => 'EMP-1', 'grades_mode' => 'single', 'grades_handled' => ['Grade 2'],
        ];
    }

    private function parentForm(array $o = []): array
    {
        return $o + ['first_name' => 'Pat', 'last_name' => 'Cruz', 'email' => 'pat@example.com', 'password' => 'Passw0rd!', 'password_confirmation' => 'Passw0rd!'];
    }

    private function unconfirmed(string $type = 'Teacher', string $email = 'pinky@example.com'): User
    {
        $u = User::create(['first_name' => 'Pinky', 'last_name' => 'Reyes', 'email' => $email, 'password' => 'Passw0rd!', 'user_type' => $type]);

        if ($type === 'Teacher') {
            Teacher::create(['user_id' => $u->id, 'school_name' => 'Rizal ES', 'employee_id' => 'EMP-1', 'status' => 'Pending', 'grades_handled' => ['Grade 1']]);
        } elseif ($type === 'Parent') {
            ParentAccount::create(['user_id' => $u->id]);
        }

        return $u;
    }

    private function link(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]);
    }

    // -------------------------------------------------------------- registration

    public function test_a_new_teacher_is_not_signed_in_and_is_told_to_open_the_link(): void
    {
        Notification::fake();

        $this->post(route('register.teacher.submit'), $this->teacherForm())
            ->assertOk()->assertSee('Almost there, Pinky!')->assertSee('pinky@example.com')->assertSee('Send it again');

        $this->assertGuest();
        $user = User::where('email', 'pinky@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_a_new_parent_is_not_signed_in_either(): void
    {
        Notification::fake();

        $this->post(route('register.parent.submit'), $this->parentForm())->assertOk()->assertSee('Almost there, Pat!');

        $this->assertGuest();
        Notification::assertSentTo(User::where('email', 'pat@example.com')->firstOrFail(), VerifyEmail::class);
    }

    public function test_with_the_rule_switched_off_registering_still_signs_the_person_in_as_before(): void
    {
        config(['auth.require_verified_email' => false]);
        Notification::fake();

        $this->post(route('register.teacher.submit'), $this->teacherForm())->assertOk()->assertSee("You're registered, Pinky!", false);

        $this->assertAuthenticated();
    }

    public function test_an_address_we_cannot_send_to_leaves_no_account_behind(): void
    {
        // The mail step blows up, as it does when the sender is down or the address is refused.
        $this->app->instance(Dispatcher::class, new class
        {
            public function send(...$args): void { throw new \RuntimeException('cannot send'); }

            public function sendNow(...$args): void { throw new \RuntimeException('cannot send'); }
        });

        $this->from(route('register.teacher'))->post(route('register.teacher.submit'), $this->teacherForm())
            ->assertRedirect(route('register.teacher'))->assertSessionHasErrors('email');

        $this->assertSame(0, User::count());
        $this->assertSame(0, Teacher::count());
        $this->assertGuest();
    }

    public function test_a_mistyped_provider_is_caught_before_anything_is_sent(): void
    {
        Notification::fake();

        $this->from(route('register.parent'))->post(route('register.parent.submit'), $this->parentForm(['email' => 'pat@gmial.com']))
            ->assertSessionHasErrors(['email' => 'That address looks mistyped. Did you mean pat@gmail.com?']);

        $this->assertSame(0, User::count());
        Notification::assertNothingSent();
    }

    public function test_a_domain_that_cannot_receive_mail_is_refused_when_the_lookup_is_on(): void
    {
        config(['auth.check_email_domain' => true]);
        Notification::fake();

        // ".invalid" is reserved and never exists, so this is refused with or without an internet connection.
        $this->from(route('register.parent'))->post(route('register.parent.submit'), $this->parentForm(['email' => 'pat@no-such-school-site.invalid']))
            ->assertSessionHasErrors('email');

        $this->assertSame(0, User::count());
    }

    public function test_the_address_is_stored_in_lower_case_and_a_second_try_in_capitals_is_a_duplicate(): void
    {
        Notification::fake();

        $this->post(route('register.parent.submit'), $this->parentForm(['email' => '  Pat@Example.COM ']))->assertOk();
        $this->assertSame('pat@example.com', User::first()->email);

        $this->from(route('register.parent'))->post(route('register.parent.submit'), $this->parentForm(['email' => 'PAT@example.com', 'first_name' => 'Other']))
            ->assertSessionHasErrors('email');
        $this->assertSame(1, User::count());
    }

    // -------------------------------------------------------------- signing in

    public function test_a_teacher_cannot_sign_in_before_confirming_but_can_ask_for_a_new_link(): void
    {
        $this->unconfirmed();

        $this->from(route('login'))->post(route('login.submit'), ['email' => 'pinky@example.com', 'password' => 'Passw0rd!'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email'])
            ->assertSessionHas('unconfirmed_email', 'pinky@example.com');

        $this->assertGuest();
        $this->assertStringContainsString('confirm your email', session('errors')->first('email'));
        $this->assertStringContainsString('pi***@example.com', session('errors')->first('email'));

        $this->get(route('login'))->assertOk();
    }

    public function test_the_login_page_offers_a_new_link_only_when_the_password_was_right(): void
    {
        $this->unconfirmed();

        // Wrong password: the same message as always and no hint that the account exists.
        $this->from(route('login'))->post(route('login.submit'), ['email' => 'pinky@example.com', 'password' => 'wrong-one'])
            ->assertSessionHasErrors(['email' => 'Invalid email or password.'])->assertSessionMissing('unconfirmed_email');

        $this->withSession(['unconfirmed_email' => 'pinky@example.com'])->get(route('login'))
            ->assertOk()->assertSee('Send me a new confirmation link');
    }

    public function test_a_parent_is_held_back_the_same_way(): void
    {
        $this->unconfirmed('Parent', 'pat@example.com');

        $this->from(route('login'))->post(route('login.submit'), ['email' => 'pat@example.com', 'password' => 'Passw0rd!'])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_after_opening_the_link_the_teacher_signs_in(): void
    {
        $teacher = $this->unconfirmed();

        $this->get($this->link($teacher))->assertOk()->assertSee('pi***@example.com')->assertDontSee('pinky@example.com');

        $this->post(route('login.submit'), ['email' => 'pinky@example.com', 'password' => 'Passw0rd!'])->assertRedirect(route('teacher.dashboard'));
        $this->assertAuthenticated();
    }

    public function test_the_admin_is_never_held_back(): void
    {
        User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.com', 'password' => 'a-private-and-long-one-9', 'user_type' => 'Admin']);

        $this->post(route('login.submit'), ['email' => 'ada@example.com', 'password' => 'a-private-and-long-one-9'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_with_the_rule_switched_off_an_unconfirmed_teacher_signs_in_as_before(): void
    {
        config(['auth.require_verified_email' => false]);
        $this->unconfirmed();

        $this->post(route('login.submit'), ['email' => 'pinky@example.com', 'password' => 'Passw0rd!'])->assertRedirect(route('teacher.dashboard'));
    }

    // -------------------------------------------------------------- the link and sending it again

    public function test_a_link_that_does_not_work_says_so_and_offers_a_new_one(): void
    {
        $teacher = $this->unconfirmed();
        $url = $this->link($teacher);

        $this->travel(61)->minutes();

        $this->get($url)->assertForbidden()->assertSee('This link did not work')->assertSee('Send me a new link');
        $this->assertNull($teacher->fresh()->email_verified_at);
    }

    public function test_asking_for_a_new_link_sends_one_and_gives_the_same_answer_for_any_address(): void
    {
        Notification::fake();
        $teacher = $this->unconfirmed();
        $message = 'If that address has an account that is still waiting to be confirmed, we sent a new link.';

        $this->post(route('verification.resend'), ['email' => 'pinky@example.com'])->assertRedirect(route('login'))->assertSessionHas('status');
        Notification::assertSentToTimes($teacher, VerifyEmail::class, 1);

        // A stranger's address gets the very same words and nothing is sent.
        $this->post(route('verification.resend'), ['email' => 'nobody@example.com'])->assertRedirect(route('login'));
        $this->assertStringStartsWith($message, session('status'));
    }

    public function test_a_new_link_is_sent_only_once_a_minute_and_never_to_confirmed_people_or_the_admin(): void
    {
        Notification::fake();
        $teacher = $this->unconfirmed();

        $this->post(route('verification.resend'), ['email' => 'pinky@example.com']);
        $this->post(route('verification.resend'), ['email' => 'pinky@example.com']);
        Notification::assertSentToTimes($teacher, VerifyEmail::class, 1);

        $confirmed = $this->unconfirmed('Parent', 'pat@example.com');
        $confirmed->forceFill(['email_verified_at' => now()])->save();
        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.com', 'password' => 'a-private-and-long-one-9', 'user_type' => 'Admin']);

        $this->post(route('verification.resend'), ['email' => 'pat@example.com']);
        $this->post(route('verification.resend'), ['email' => 'ada@example.com']);
        Notification::assertNotSentTo($confirmed, VerifyEmail::class);
        Notification::assertNotSentTo($admin, VerifyEmail::class);
    }

    public function test_the_helper_masks_and_suggests(): void
    {
        $this->assertSame('pi***@gmail.com', EmailAddress::mask('pinky@gmail.com'));
        $this->assertSame('a***@gmail.com', EmailAddress::mask('ab@gmail.com'));
        $this->assertSame('pat@gmail.com', EmailAddress::suggestion('PAT@gmial.com'));
        $this->assertNull(EmailAddress::suggestion('pat@gmail.com'));
        $this->assertNull(EmailAddress::suggestion('not-an-email'));
    }
}

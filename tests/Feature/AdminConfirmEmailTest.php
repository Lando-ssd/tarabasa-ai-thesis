<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\ParentAccount;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "Confirm email myself": the Admin can confirm someone's email address when the link never reaches them and the
 * Admin has checked the address another way. The reason is required, the action is written to the Activity log, and
 * it can never be used on an Admin account or an address that is already confirmed.
 */
class AdminConfirmEmailTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function admin(): User
    {
        $u = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada-admin@example.com', 'password' => 'a-private-and-long-one-9', 'user_type' => 'Admin']);
        $u->forceFill(['email_verified_at' => now()])->save();

        return $u;
    }

    private function teacher(bool $verified = false): Teacher
    {
        $n = ++$this->seq;
        $u = User::create(['first_name' => "Tess{$n}", 'last_name' => 'Teacher', 'email' => "tess{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $u->forceFill(['email_verified_at' => $verified ? now() : null])->save();

        return Teacher::create(['user_id' => $u->id, 'school_name' => 'Rizal ES', 'employee_id' => "EMP-{$n}", 'status' => 'Pending', 'grades_handled' => ['Grade 1']]);
    }

    private function parentUser(): User
    {
        $n = ++$this->seq;
        $u = User::create(['first_name' => "Pat{$n}", 'last_name' => 'Parent', 'email' => "pat{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        ParentAccount::create(['user_id' => $u->id]);

        return $u;
    }

    public function test_the_admin_confirms_a_teacher_with_a_reason_and_it_is_logged(): void
    {
        $teacher = $this->teacher();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.users.confirm-email', $teacher->user), ['reason' => 'I spoke with them', 'note' => 'Phone call, Monday'])
            ->assertSessionHas('status');

        $this->assertNotNull($teacher->user->fresh()->email_verified_at);

        $row = AdminAction::where('action', 'confirmed_email')->firstOrFail();
        $this->assertSame($admin->id, $row->admin_user_id);
        $this->assertSame($teacher->user->id, $row->target_user_id);
        $this->assertSame('I spoke with them', $row->detail);
        $this->assertSame('Phone call, Monday', $row->note);

        $this->actingAs($admin)->get(route('admin.log'))->assertOk()->assertSee('confirmed the email address of')->assertSee('I spoke with them');
    }

    public function test_after_confirming_the_teacher_can_be_approved_and_can_log_in(): void
    {
        config(['auth.require_verified_email' => true]);
        $teacher = $this->teacher();
        $admin = $this->admin();

        // Before: locked on both sides.
        $this->actingAs($admin)->post(route('admin.teachers.activate', $teacher))->assertSessionHasErrors('admin');
        $this->post(route('logout'));
        $this->post(route('login.submit'), ['email' => $teacher->user->email, 'password' => 'Passw0rd!'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->actingAs($admin)->post(route('admin.users.confirm-email', $teacher->user), ['reason' => 'I checked with their school']);
        $this->actingAs($admin)->post(route('admin.teachers.activate', $teacher));
        $this->assertSame('Active', $teacher->fresh()->status);

        $this->post(route('logout'));
        $this->post(route('login.submit'), ['email' => $teacher->user->email, 'password' => 'Passw0rd!'])->assertRedirect(route('teacher.dashboard'));
    }

    public function test_a_parent_can_be_confirmed_too(): void
    {
        $parent = $this->parentUser();

        $this->actingAs($this->admin())->post(route('admin.users.confirm-email', $parent), ['reason' => 'Their email blocks our messages'])->assertSessionHas('status');

        $this->assertNotNull($parent->fresh()->email_verified_at);
    }

    public function test_a_reason_is_required_and_other_needs_a_note(): void
    {
        $teacher = $this->teacher();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.users.confirm-email', $teacher->user), [])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('admin.users.confirm-email', $teacher->user), ['reason' => 'because'])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('admin.users.confirm-email', $teacher->user), ['reason' => 'Other'])->assertSessionHasErrors('note');

        $this->assertNull($teacher->user->fresh()->email_verified_at);
        $this->assertSame(0, AdminAction::where('action', 'confirmed_email')->count());

        $this->actingAs($admin)->post(route('admin.users.confirm-email', $teacher->user), ['reason' => 'Other', 'note' => 'Verified by the principal'])->assertSessionHas('status');
        $this->assertNotNull($teacher->user->fresh()->email_verified_at);
    }

    public function test_an_address_that_is_already_confirmed_is_not_confirmed_again(): void
    {
        $teacher = $this->teacher(verified: true);

        $this->actingAs($this->admin())->post(route('admin.users.confirm-email', $teacher->user), ['reason' => 'I spoke with them'])->assertSessionHasErrors('admin');

        $this->assertSame(0, AdminAction::where('action', 'confirmed_email')->count());
    }

    public function test_it_can_never_be_used_on_an_admin_account(): void
    {
        $admin = $this->admin();
        $other = User::create(['first_name' => 'Other', 'last_name' => 'Admin', 'email' => 'other-admin@example.com', 'password' => 'a-private-and-long-one-9', 'user_type' => 'Admin']);

        $this->actingAs($admin)->post(route('admin.users.confirm-email', $other), ['reason' => 'I spoke with them'])->assertForbidden();

        $this->assertNull($other->fresh()->email_verified_at);
    }

    public function test_only_the_admin_can_press_it(): void
    {
        $teacher = $this->teacher();
        $parent = $this->parentUser();
        $url = route('admin.users.confirm-email', $parent);

        $this->post($url, ['reason' => 'I spoke with them'])->assertRedirect(route('login'));
        $this->actingAs($teacher->user)->post($url, ['reason' => 'I spoke with them'])->assertForbidden();
        $this->actingAs($parent)->post($url, ['reason' => 'I spoke with them'])->assertForbidden();

        $this->assertNull($parent->fresh()->email_verified_at);
    }

    public function test_the_screens_offer_it_only_where_the_email_is_not_confirmed(): void
    {
        $waiting = $this->teacher();
        $confirmed = $this->teacher(verified: true);
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.approvals'))->assertOk()
            ->assertSee('Confirm email myself')->assertSee('id="ce-'.$waiting->user->id.'"', false)->assertDontSee('id="ce-'.$confirmed->user->id.'"', false)
            ->assertSee($waiting->user->email);

        $this->actingAs($admin)->get(route('admin.accounts'))->assertOk()
            ->assertSee('Confirm email myself')->assertSee('id="ce-'.$waiting->user->id.'"', false)->assertDontSee('id="ce-'.$confirmed->user->id.'"', false);
    }

    public function test_sending_the_email_again_says_which_address_it_went_to(): void
    {
        Notification::fake();
        $teacher = $this->teacher();

        $this->actingAs($this->admin())->post(route('admin.users.resend-verification', $teacher->user))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'te***@example.com') && ! str_contains($s, 'tess1@example.com'));

        Notification::assertSentTo($teacher->user, VerifyEmail::class);
    }
}

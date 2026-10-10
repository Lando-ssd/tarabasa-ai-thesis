<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\ServiceFailure;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The rebuilt Admin: Overview, Approvals, Accounts, System health and Activity log. The rules that matter are checked
 * on the server (not only hidden in the page): only a waiting teacher can be approved or rejected, approval needs a
 * verified email, a rejected teacher is told why, and every change is written to the activity log.
 */
class AdminRedesignTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function admin(): User
    {
        $u = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada-admin@example.com', 'password' => 'a-private-and-long-one-9', 'user_type' => 'Admin']);

        $u->forceFill(['email_verified_at' => now()])->save();

        return $u;
    }

    private function teacher(string $status = 'Pending', bool $verified = true, ?string $reason = null): Teacher
    {
        $n = ++$this->seq;
        $u = User::create(['first_name' => "Tess{$n}", 'last_name' => 'Teacher', 'email' => "tess{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $u->forceFill(['email_verified_at' => $verified ? now() : null])->save();

        return Teacher::create(['user_id' => $u->id, 'school_name' => 'Rizal ES', 'employee_id' => "EMP-{$n}", 'status' => $status, 'rejection_reason' => $reason, 'grades_handled' => ['Grade 1']]);
    }

    private function parent(array $o = []): User
    {
        $n = ++$this->seq;
        $u = User::create($o + ['first_name' => "Pat{$n}", 'last_name' => 'Parent', 'email' => "pat{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $u->forceFill(['email_verified_at' => now()])->save();

        return $u;
    }

    // ------------------------------------------------------------- who may see it

    public function test_only_the_admin_can_open_the_screens_or_press_the_buttons(): void
    {
        $teacher = $this->teacher('Active');
        $target = $this->teacher();
        $routes = [
            ['get', route('admin.dashboard')], ['get', route('admin.approvals')], ['get', route('admin.accounts')], ['get', route('admin.health')], ['get', route('admin.log')],
            ['post', route('admin.teachers.activate', $target)], ['post', route('admin.teachers.reject', $target)], ['post', route('admin.teachers.reopen', $target)],
            ['post', route('admin.users.toggle-status', $target->user)], ['post', route('admin.users.resend-verification', $target->user)], ['post', route('admin.service-check')],
        ];

        foreach ($routes as [$method, $url]) {
            $this->flushSession();
            $this->$method($url)->assertRedirect(route('login'));
        }

        $this->actingAs($teacher->user);
        foreach ($routes as [$method, $url]) {
            $this->$method($url)->assertForbidden();
        }

        $this->assertSame('Pending', $target->fresh()->status, 'nothing changed');
        $this->assertSame(0, AdminAction::count());
    }

    // ------------------------------------------------------------- approving

    public function test_approving_a_waiting_teacher_with_a_verified_email_works_and_is_logged(): void
    {
        $admin = $this->admin();
        $t = $this->teacher('Pending', true);

        $this->actingAs($admin)->post(route('admin.teachers.activate', $t))->assertRedirect()->assertSessionHas('status');

        $this->assertSame('Active', $t->fresh()->status);
        $this->assertNull($t->fresh()->rejection_reason);
        $action = AdminAction::firstOrFail();
        $this->assertSame(['approved', 'Rizal ES', $admin->id, $t->user_id], [$action->action, $action->detail, $action->admin_user_id, $action->target_user_id]);
        $this->assertSame('Ada Admin', $action->admin_name);
    }

    public function test_a_teacher_who_has_not_verified_their_email_cannot_be_approved(): void
    {
        $t = $this->teacher('Pending', false);

        $this->actingAs($this->admin())->post(route('admin.teachers.activate', $t))->assertRedirect()->assertSessionHasErrors('admin');

        $this->assertSame('Pending', $t->fresh()->status);
        $this->assertSame(0, AdminAction::count());
        $this->get(route('admin.approvals'))->assertOk()->assertSee('Email not verified')->assertSee('Send the email again');
    }

    public function test_only_a_waiting_teacher_can_be_approved_or_rejected_even_when_the_address_is_typed_in_by_hand(): void
    {
        $admin = $this->admin();
        $active = $this->teacher('Active');
        $rejected = $this->teacher('Rejected', true, 'Duplicate account');

        $this->actingAs($admin);
        $this->post(route('admin.teachers.reject', $active), ['reason' => 'Duplicate account'])->assertSessionHasErrors('admin');
        $this->post(route('admin.teachers.activate', $rejected))->assertSessionHasErrors('admin');
        $this->post(route('admin.teachers.reopen', $active))->assertSessionHasErrors('admin');

        $this->assertSame('Active', $active->fresh()->status, 'an Active teacher cannot be locked out by a stray request');
        $this->assertSame('Rejected', $rejected->fresh()->status, 'a rejected teacher is not approved by a stray request');
        $this->assertSame(0, AdminAction::count());
    }

    // ------------------------------------------------------------- rejecting, and the reason the teacher sees

    public function test_rejecting_needs_a_listed_reason_and_other_needs_a_note(): void
    {
        $t = $this->teacher();
        $this->actingAs($this->admin());

        $this->post(route('admin.teachers.reject', $t), [])->assertSessionHasErrors('reason');
        $this->post(route('admin.teachers.reject', $t), ['reason' => 'Because I said so'])->assertSessionHasErrors('reason');
        $this->post(route('admin.teachers.reject', $t), ['reason' => 'Other'])->assertSessionHasErrors('note');
        $this->post(route('admin.teachers.reject', $t), ['reason' => 'Duplicate account', 'note' => str_repeat('x', 141)])->assertSessionHasErrors('note');

        $this->assertSame('Pending', $t->fresh()->status);
    }

    public function test_a_rejected_teacher_sees_the_reason_when_they_try_to_sign_in_and_the_action_is_logged(): void
    {
        $t = $this->teacher();

        $this->actingAs($this->admin())->post(route('admin.teachers.reject', $t), ['reason' => 'Not a teacher at this school', 'note' => 'Not on the list'])->assertRedirect();

        $this->assertSame('Rejected', $t->fresh()->status);
        $this->assertSame('Not a teacher at this school: Not on the list', $t->fresh()->rejection_reason);
        $log = AdminAction::firstOrFail();
        $this->assertSame(['rejected', 'Not a teacher at this school: Not on the list'], [$log->action, $log->note]);

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->from(route('login'))->post(route('login.submit'), ['email' => $t->user->email, 'password' => 'Passw0rd!', 'role' => 'teacher'])
            ->assertSessionHasErrors(['email' => 'Your teacher registration was not approved. Reason: Not a teacher at this school: Not on the list. Contact your school\'s TaraBasa admin.']);
    }

    public function test_a_teacher_rejected_before_reasons_existed_still_gets_the_plain_message(): void
    {
        $t = $this->teacher('Rejected', true, null);

        $this->from(route('login'))->post(route('login.submit'), ['email' => $t->user->email, 'password' => 'Passw0rd!', 'role' => 'teacher'])
            ->assertSessionHasErrors(['email' => 'Your teacher registration was not approved. Contact your school\'s TaraBasa admin.']);
    }

    public function test_other_with_a_note_stores_just_the_note(): void
    {
        $t = $this->teacher();

        $this->actingAs($this->admin())->post(route('admin.teachers.reject', $t), ['reason' => 'Other', 'note' => 'The school asked us to remove this registration'])->assertRedirect();

        $this->assertSame('The school asked us to remove this registration', $t->fresh()->rejection_reason);
    }

    // ------------------------------------------------------------- reopening

    public function test_a_rejected_teacher_can_be_brought_back_to_the_waiting_list(): void
    {
        $t = $this->teacher('Rejected', true, 'Duplicate account');

        $this->actingAs($this->admin())->post(route('admin.teachers.reopen', $t))->assertRedirect();

        $this->assertSame('Pending', $t->fresh()->status);
        $this->assertNull($t->fresh()->rejection_reason);
        $this->assertSame('reopened', AdminAction::firstOrFail()->action);
        $this->get(route('admin.approvals'))->assertSee($t->user->first_name);
    }

    // ------------------------------------------------------------- accounts

    public function test_deactivating_and_activating_are_logged_with_the_note_and_an_admin_cannot_be_targeted(): void
    {
        $admin = $this->admin();
        $parent = $this->parent();

        $this->actingAs($admin)->post(route('admin.users.toggle-status', $parent), ['note' => 'Asked to close the account'])->assertRedirect();
        $this->assertSame('Inactive', $parent->fresh()->status);
        $this->post(route('admin.users.toggle-status', $parent))->assertRedirect();
        $this->assertSame('Active', $parent->fresh()->status);

        $this->assertSame(['activated', 'deactivated'], AdminAction::orderByDesc('id')->pluck('action')->all());
        $this->assertSame('Asked to close the account', AdminAction::where('action', 'deactivated')->value('note'));

        $this->post(route('admin.users.toggle-status', $admin))->assertForbidden();
        $this->post(route('admin.users.resend-verification', $admin))->assertForbidden();
    }

    public function test_the_accounts_screen_filters_counts_and_never_lists_an_admin(): void
    {
        $this->actingAs($this->admin());
        $t = $this->teacher('Active');
        $p1 = $this->parent(['first_name' => 'Zed']);
        $p2 = $this->parent(['first_name' => 'Yan', 'status' => 'Inactive']);

        $all = $this->get(route('admin.accounts'))->assertOk();
        $all->assertSee('Zed')->assertSee('Yan')->assertSee($t->user->first_name)->assertDontSee('ada-admin@example.com');
        $this->get(route('admin.accounts', ['filter' => 'parents']))->assertSee('Zed')->assertDontSee($t->user->first_name);
        $this->get(route('admin.accounts', ['filter' => 'teachers']))->assertSee($t->user->first_name)->assertDontSee('Zed');
        $this->get(route('admin.accounts', ['filter' => 'inactive']))->assertSee('Yan')->assertDontSee('Zed');
        $this->get(route('admin.accounts', ['q' => 'zed']))->assertSee('Zed')->assertDontSee('Yan');
        $this->get(route('admin.accounts', ['filter' => 'nonsense']))->assertOk(); // an unknown filter just shows everyone
    }

    public function test_the_admin_can_send_the_verification_email_again_once_a_minute(): void
    {
        Notification::fake();
        $unverified = $this->teacher('Pending', false)->user;
        $verified = $this->parent();
        $this->actingAs($this->admin());

        $this->post(route('admin.users.resend-verification', $unverified))->assertSessionHas('status');
        Notification::assertSentTo($unverified, VerifyEmail::class);
        $this->assertSame('resent', AdminAction::firstOrFail()->action);

        $this->post(route('admin.users.resend-verification', $unverified))->assertSessionHasErrors('admin');
        Notification::assertSentToTimes($unverified, VerifyEmail::class, 1);

        $this->post(route('admin.users.resend-verification', $verified))->assertSessionHasErrors('admin');
        Notification::assertNotSentTo($verified, VerifyEmail::class);
    }

    // ------------------------------------------------------------- overview, menu, log

    public function test_the_overview_lists_what_needs_the_admin_and_the_menu_counts_match(): void
    {
        $this->actingAs($this->admin());
        $this->teacher('Pending', true);
        $this->teacher('Pending', false);

        $page = $this->get(route('admin.dashboard'))->assertOk();
        $page->assertSee('2 teachers waiting for approval')->assertSee('1 of them has not verified their email yet')->assertDontSee('Nothing needs you right now');
        $this->assertStringContainsString('<span class="nav-count ">2</span>', $page->getContent(), 'the menu shows how many wait');
        $page->assertDontSee('nav-count red');

        $this->get(route('admin.approvals'))->assertSee('Waiting')->assertSee('Rejected');
    }

    public function test_a_service_that_is_only_asleep_is_not_a_need_but_a_broken_one_is(): void
    {
        $this->actingAs($this->admin());
        $asleep = fn ($key, $who) => ['key' => $key, 'who' => $who, 'what' => 'GET /health', 'state' => 'asleep', 'http' => 429, 'ms' => 100, 'said' => 'Too Many Requests'];

        ServiceFailure::record('check', null, 'Service check from the app server: nothing is broken.', [], 'x', ['problems' => 0, 'notices' => 3, 'checks' => [
            $asleep('reader', 'Reading checker'), $asleep('generator', 'Activity generator'), $asleep('recommender', 'Adaptive recommender'),
        ]]);

        $page = $this->get(route('admin.dashboard'))->assertOk();
        $page->assertSee('Nothing needs you right now')->assertDontSee('nav-count red');
        $this->get(route('admin.health'))->assertOk()->assertSee('Asleep')->assertDontSee('Needs you');

        ServiceFailure::record('check', null, 'Service check from the app server: 1 of 4 checks had a problem.', [], 'x', ['problems' => 1, 'notices' => 0, 'checks' => [
            ['key' => 'generator', 'who' => 'Activity generator', 'what' => 'GET /health', 'state' => 'problem', 'http' => 502, 'ms' => 100, 'said' => 'Bad gateway'],
        ]]);

        $page = $this->get(route('admin.dashboard'))->assertOk();
        $page->assertSee('One service has a problem')->assertSee('Activity generator')->assertSee('nav-count red');
        $this->get(route('admin.health'))->assertSee('Needs you')->assertSee('the service answered 502');
    }

    public function test_failed_emails_make_the_email_card_say_so_and_offer_to_reconnect_gmail(): void
    {
        config(['services.gmail_send.client_id' => 'id', 'services.gmail_send.client_secret' => 'secret']);
        $this->actingAs($this->admin());

        ServiceFailure::record('mail', null, 'Gmail refused the saved sign in (invalid_grant)', [], 'Token has been expired or revoked.');

        $page = $this->get(route('admin.health'))->assertOk();
        $page->assertSee('Email sending')->assertSee('Needs you')->assertSee('Gmail refused the saved sign in')->assertSee('Reconnect Gmail');
        $this->get(route('admin.dashboard'))->assertSee('Verification emails may not be going out');
    }

    public function test_the_activity_log_shows_who_did_what_with_the_reason_newest_first(): void
    {
        $admin = $this->admin();
        $a = $this->teacher('Pending', true);
        $b = $this->teacher('Pending', true);
        $this->actingAs($admin);

        $this->post(route('admin.teachers.activate', $a));
        $this->post(route('admin.teachers.reject', $b), ['reason' => 'Duplicate account']);

        $page = $this->get(route('admin.log'))->assertOk();
        $page->assertSeeInOrder(['Ada Admin', 'rejected', $b->user->first_name, 'Reason: Duplicate account', 'Ada Admin', 'approved', $a->user->first_name]);
        $this->get(route('admin.dashboard'))->assertSee('Latest admin actions')->assertSee('Duplicate account');
    }

    public function test_the_log_still_reads_correctly_after_the_account_it_names_is_deleted(): void
    {
        $admin = $this->admin();
        $t = $this->teacher('Pending', true);
        $this->actingAs($admin)->post(route('admin.teachers.activate', $t));

        $name = $t->user->first_name;
        Teacher::where('id', $t->id)->delete();
        $t->user->delete();

        $this->get(route('admin.log'))->assertOk()->assertSee($name)->assertSee('approved');
        $this->assertNull(AdminAction::first()->target_user_id, 'the link goes away, the name stays');
    }
}

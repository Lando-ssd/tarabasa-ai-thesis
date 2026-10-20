<?php

namespace Tests\Feature;

use App\Models\AdminAction;
use App\Models\Notification;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\GradeChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * "I handle another grade": taking a grade away (or keeping the same ones) applies at once, but ADDING a grade by an approved teacher is a
 * request the Admin confirms with the school. The grades a teacher may open classes for only change when the Admin approves. Every rule is
 * on the server (AccountProfileService, GradeChangeService), on the website and on the phone.
 */
class GradeRequestTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function admin(): User
    {
        $u = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada-gr@example.com', 'password' => 'a-private-and-long-one-9', 'user_type' => 'Admin']);
        $u->forceFill(['email_verified_at' => now()])->save();

        return $u;
    }

    /** @return array{0: User, 1: Teacher} */
    private function teacher(?array $grades, string $status = 'Active'): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => "Tess{$n}", 'last_name' => 'Teacher', 'email' => "gr{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "GR-{$n}", 'status' => $status, 'free_generation_credits_remaining' => 2, 'grades_handled' => $grades]);

        return [$user, $teacher];
    }

    private const NOTE = 'Mrs. Cruz, the school head, gave me Grade 2 this year.';

    // ------------------------------------------------------------------ the teacher asks

    public function test_an_approved_teacher_cannot_give_themselves_another_grade_and_must_say_who_can_confirm_it(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $this->actingAs($user);

        $this->from('/x')->put(route('profile.grades.update'), ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2']])->assertSessionHasErrors('grades_request_note');
        $this->assertSame(['Grade 1'], $t->fresh()->grades_handled);
        $this->assertSame([], $t->fresh()->gradesPending());

        $this->from('/x')->put(route('profile.grades.update'), ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2'], 'grades_request_note' => 'ok'])->assertSessionHasErrors('grades_request_note');
        $this->assertNull($t->fresh()->grades_requested_at);
    }

    public function test_with_a_note_the_request_waits_and_the_grades_do_not_change(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $this->actingAs($user);

        $this->put(route('profile.grades.update'), ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2'], 'grades_request_note' => self::NOTE])
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Sent to the Admin') && str_contains($m, 'Grade 2') && str_contains($m, 'Until then you can open classes for Grade 1 only'));

        $fresh = $t->fresh();
        $this->assertSame(['Grade 1'], $fresh->grades_handled);
        $this->assertSame(['Grade 1', 'Grade 2'], $fresh->gradesPending());
        $this->assertSame(self::NOTE, $fresh->grades_request_note);
        $this->assertNotNull($fresh->grades_requested_at);

        // A Grade 2 class still cannot be opened.
        $this->from('/x')->post(route('teacher.classes.store'), ['name' => 'Dahlia', 'section' => 'A', 'grade_level' => 'Grade 2', 'school_year' => SchoolClass::currentSchoolYear(), 'group_tag' => ''])->assertSessionHasErrors('grade_level');
        $this->assertSame(0, SchoolClass::where('teacher_id', $t->id)->count());
    }

    public function test_taking_a_grade_away_applies_at_once_and_replaces_an_open_request(): void
    {
        [$user, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $t->update(['grades_requested' => ['Grade 1', 'Grade 2', 'Grade 3'], 'grades_requested_at' => now(), 'grades_request_note' => self::NOTE]);
        $this->actingAs($user);

        $this->put(route('profile.grades.update'), ['grades_mode' => 'single', 'grades_handled' => ['Grade 2']])->assertSessionHas('status', fn ($m) => str_contains($m, 'Grades updated'));

        $fresh = $t->fresh();
        $this->assertSame(['Grade 2'], $fresh->grades_handled);
        $this->assertSame([], $fresh->gradesPending());
        $this->assertNull($fresh->grades_requested_at);
    }

    public function test_a_teacher_still_waiting_for_approval_saves_at_once_because_the_admin_checks_the_grades_with_the_account(): void
    {
        [$user, $t] = $this->teacher(['Grade 1'], 'Pending');
        $this->actingAs($user);

        $this->put(route('profile.grades.update'), ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2']])
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'The Admin checks them with your school when approving your account'));

        $this->assertSame(['Grade 1', 'Grade 2'], $t->fresh()->grades_handled);
        $this->assertSame([], $t->fresh()->gradesPending());
    }

    public function test_a_teacher_made_before_grades_were_asked_already_handles_all_three_so_there_is_nothing_to_request(): void
    {
        [$user, $t] = $this->teacher(null);
        $this->actingAs($user);

        $this->assertSame(['Grade 1', 'Grade 2', 'Grade 3'], $t->gradesAllowed());
        $this->put(route('profile.grades.update'), ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2', 'Grade 3']])->assertSessionHas('status', fn ($m) => str_contains($m, 'Grades updated'));
        $this->assertSame([], $t->fresh()->gradesPending());
    }

    public function test_the_teacher_can_take_the_request_back(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $this->actingAs($user);
        $this->put(route('profile.grades.update'), ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2'], 'grades_request_note' => self::NOTE]);

        $this->delete(route('profile.grades.cancel'))->assertSessionHas('status', fn ($m) => str_contains($m, 'Request cancelled') && str_contains($m, 'Grade 1'));

        $this->assertSame([], $t->fresh()->gradesPending());
        $this->assertSame(['Grade 1'], $t->fresh()->grades_handled);
    }

    public function test_the_profile_shows_the_waiting_request_and_the_admins_answer(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $this->actingAs($user);
        $this->put(route('profile.grades.update'), ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2'], 'grades_request_note' => self::NOTE]);

        $html = $this->get(route('teacher.profile.edit'))->assertOk()->getContent();
        $this->assertStringContainsString('Waiting for the Admin.', $html);
        $this->assertStringContainsString(self::NOTE, $html);
        $this->assertStringContainsString('Cancel this request', $html);

        $admin = $this->admin();
        app(GradeChangeService::class)->decline($admin, $t->fresh(), 'The school head did not confirm it', null);
        // A real request starts from a fresh copy of the user; the test's signed-in object would still hold the old teacher record.
        $this->actingAs(User::find($user->id));
        $html = $this->get(route('teacher.profile.edit'))->getContent();
        $this->assertStringNotContainsString('Waiting for the Admin.', $html);
        $this->assertStringContainsString('did not confirm Grade 2 yet', $html);
    }

    // ------------------------------------------------------------------ the Admin decides

    public function test_the_admin_approves_and_only_then_can_the_teacher_open_a_class_for_the_new_grade(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $this->actingAs($user)->put(route('profile.grades.update'), ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2'], 'grades_request_note' => self::NOTE]);
        $admin = $this->admin();
        $year = SchoolClass::currentSchoolYear();

        $this->actingAs($admin)->post(route('admin.teachers.grades.approve', $t))->assertRedirect(route('admin.approvals', ['tab' => 'grades']))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'now handles Grade 1, Grade 2'));

        $fresh = $t->fresh();
        $this->assertSame(['Grade 1', 'Grade 2'], $fresh->grades_handled);
        $this->assertSame([], $fresh->gradesPending());
        $this->assertNull($fresh->grades_request_note);

        // Written to the activity log with the Admin's name, and the teacher is told.
        $action = AdminAction::where('action', 'approved_grades')->first();
        $this->assertNotNull($action);
        $this->assertSame('Ada Admin', $action->admin_name);
        $this->assertSame('Now handles Grade 1, Grade 2', $action->detail);
        $note = Notification::where('recipient_user_id', $user->id)->where('type', Notification::TYPE_GRADES_APPROVED)->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('You can now open a class for Grade 2', $note->message);

        // And now the class can be opened, for that grade only.
        $this->actingAs(User::find($user->id))->post(route('teacher.classes.store'), ['name' => 'Dahlia', 'section' => 'A', 'grade_level' => 'Grade 2', 'school_year' => $year, 'group_tag' => ''])->assertSessionHasNoErrors();
        $this->assertSame(1, SchoolClass::where('teacher_id', $t->id)->where('grade_level', 'Grade 2')->count());
        $this->from('/x')->post(route('teacher.classes.store'), ['name' => 'Rose', 'section' => 'A', 'grade_level' => 'Grade 3', 'school_year' => $year, 'group_tag' => ''])->assertSessionHasErrors('grade_level');
    }

    public function test_the_admin_declines_with_a_reason_the_teacher_is_told_and_nothing_changes(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $this->actingAs($user)->put(route('profile.grades.update'), ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2'], 'grades_request_note' => self::NOTE]);
        $admin = $this->actingAs($this->admin());

        $admin->from('/x')->post(route('admin.teachers.grades.decline', $t), [])->assertSessionHasErrors('reason');
        $admin->from('/x')->post(route('admin.teachers.grades.decline', $t), ['reason' => 'Other'])->assertSessionHasErrors('note');
        $admin->from('/x')->post(route('admin.teachers.grades.decline', $t), ['reason' => 'Made up'])->assertSessionHasErrors('reason');
        $this->assertNotNull($t->fresh()->grades_requested_at, 'a refused decision changes nothing');

        $admin->post(route('admin.teachers.grades.decline', $t), ['reason' => 'The school head did not confirm it', 'note' => 'called on Monday'])->assertRedirect(route('admin.approvals', ['tab' => 'grades']));

        $fresh = $t->fresh();
        $this->assertSame(['Grade 1'], $fresh->grades_handled);
        $this->assertSame([], $fresh->gradesPending());

        $action = AdminAction::where('action', 'declined_grades')->first();
        $this->assertSame('The school head did not confirm it: called on Monday', $action->note);
        $note = Notification::where('recipient_user_id', $user->id)->where('type', Notification::TYPE_GRADES_DECLINED)->first();
        $this->assertStringContainsString('did not confirm Grade 2 yet', $note->message);
        $this->assertStringContainsString('The school head did not confirm it: called on Monday', $note->message);
        $this->assertStringContainsString('Your grades stay Grade 1', $note->message);
    }

    public function test_a_request_can_only_be_decided_once_and_never_by_a_teacher(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        [$other] = $this->teacher(['Grade 1']);
        $this->actingAs($user)->put(route('profile.grades.update'), ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2'], 'grades_request_note' => self::NOTE]);

        // A teacher (even the one asking) cannot approve their own request.
        $this->post(route('admin.teachers.grades.approve', $t))->assertForbidden();
        $this->actingAs($other)->post(route('admin.teachers.grades.approve', $t))->assertForbidden();
        $this->assertSame(['Grade 1'], $t->fresh()->grades_handled);

        $this->actingAs($this->admin());
        $this->post(route('admin.teachers.grades.approve', $t))->assertSessionHasNoErrors();
        $this->from('/x')->post(route('admin.teachers.grades.approve', $t))->assertSessionHasErrors('admin');
        $this->assertSame(1, AdminAction::where('action', 'approved_grades')->count(), 'approving twice writes one log line');
        $this->assertSame(1, Notification::where('type', Notification::TYPE_GRADES_APPROVED)->count());

        // A teacher with nothing asked for has nothing to approve or decline.
        $this->from('/x')->post(route('admin.teachers.grades.approve', $other->teacher ?? Teacher::where('user_id', $other->id)->first()))->assertSessionHasErrors('admin');
    }

    public function test_a_request_cancelled_while_the_admin_looks_at_it_cannot_be_approved(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $this->actingAs($user)->put(route('profile.grades.update'), ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2'], 'grades_request_note' => self::NOTE]);
        $this->delete(route('profile.grades.cancel'));

        $this->actingAs($this->admin())->from('/x')->post(route('admin.teachers.grades.approve', $t))->assertSessionHasErrors('admin');
        $this->assertSame(['Grade 1'], $t->fresh()->grades_handled);
    }

    // ------------------------------------------------------------------ what the Admin sees

    public function test_the_admin_sees_the_request_with_the_note_the_count_and_a_line_on_the_overview(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $this->actingAs($user)->put(route('profile.grades.update'), ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2'], 'grades_request_note' => self::NOTE]);
        $this->teacher(['Grade 1'], 'Pending');
        $this->actingAs($this->admin());

        $html = $this->get(route('admin.approvals', ['tab' => 'grades']))->assertOk()->getContent();
        $this->assertStringContainsString('Grade requests', $html);
        $this->assertStringContainsString('Handles now', $html);
        $this->assertStringContainsString('Asks to add', $html);
        $this->assertStringContainsString(self::NOTE, $html);
        $this->assertStringContainsString('How to check a grade request', $html);
        $this->assertStringContainsString(route('admin.teachers.grades.approve', $t), $html);
        $this->assertStringContainsString(route('admin.teachers.grades.decline', $t), $html);

        // The menu count is the new teacher waiting plus the grade request.
        $this->assertSame(2, \App\Support\AdminNav::counts()['waiting']);
        $this->assertSame(1, GradeChangeService::pendingCount());

        $overview = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('1 grade request waiting', $overview);

        // The ordinary waiting list says that a new teacher's grades are part of the check.
        $waiting = $this->get(route('admin.approvals'))->getContent();
        $this->assertStringContainsString('Grades they say they handle', $waiting);
        $this->assertStringContainsString('confirm them with the school too', $waiting);
    }

    public function test_the_activity_log_and_the_teachers_alerts_list_read_naturally(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $this->actingAs($user)->put(route('profile.grades.update'), ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2'], 'grades_request_note' => self::NOTE]);
        $this->actingAs($this->admin())->post(route('admin.teachers.grades.approve', $t));

        $log = $this->get(route('admin.log'))->assertOk()->getContent();
        $this->assertStringContainsString('approved the extra grades of', $log);

        $alerts = $this->actingAs($user)->get(route('teacher.notifications.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Grades confirmed', $alerts);
    }

    // ------------------------------------------------------------------ the phone app

    public function test_the_phone_asks_cancels_and_reads_the_same_state(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile/grades', ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2']])->assertStatus(422)->assertJsonValidationErrors('grades_request_note');

        $r = $this->putJson('/api/auth/profile/grades', ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2'], 'grades_request_note' => self::NOTE])->assertOk();
        $this->assertSame(['Grade 1'], $r->json('account.teacher.grades'));
        $this->assertSame(['Grade 1', 'Grade 2'], $r->json('account.teacher.gradesPending.grades'));
        $this->assertSame(['Grade 2'], $r->json('account.teacher.gradesPending.added'));
        $this->assertSame(self::NOTE, $r->json('account.teacher.gradesPending.note'));

        // /me reports it too (so the app shows it after a restart).
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('account.teacher.gradesPending.added.0', 'Grade 2');

        $c = $this->postJson('/api/auth/profile/grades/cancel')->assertOk();
        $this->assertNull($c->json('account.teacher.gradesPending'));
        $this->assertSame(['Grade 1'], $c->json('account.teacher.grades'));
    }

    public function test_the_phone_shows_the_admins_answer_after_approval(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        Sanctum::actingAs($user);
        $this->putJson('/api/auth/profile/grades', ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2'], 'grades_request_note' => self::NOTE])->assertOk();

        app(GradeChangeService::class)->approve($this->admin(), $t->fresh());
        Sanctum::actingAs(User::find($user->id));

        $me = $this->getJson('/api/auth/me')->assertOk();
        $this->assertSame(['Grade 1', 'Grade 2'], $me->json('account.teacher.grades'));
        $this->assertNull($me->json('account.teacher.gradesPending'));
        $this->assertTrue($me->json('account.teacher.gradesDecision.approved'));
        $this->assertStringContainsString('The Admin confirmed your grades', $me->json('account.teacher.gradesDecision.message'));
    }

    public function test_a_parent_cannot_use_the_grade_endpoints(): void
    {
        $user = User::create(['first_name' => 'Pam', 'last_name' => 'Dee', 'email' => 'pam.gr@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $user->forceFill(['email_verified_at' => now()])->save();
        \App\Models\ParentAccount::create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/profile/grades/cancel')->assertStatus(403);
    }
}

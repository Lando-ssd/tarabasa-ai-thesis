<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\ParentAccount;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The phone app's "Remove from class" and "Delete class" (/api/teacher/...): the same rules as the website, over JSON. Neither deletes a
 * child. A class with learners is deleted only when its name is sent. A class of a past school year is locked. Another teacher's class
 * is a 404, a Pending teacher, a Parent and a child are refused.
 */
class TeacherClassDeleteApiTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function api(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    /** @return array{0: User, 1: Teacher, 2: string} */
    private function teacher(string $status = 'Active'): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "tcd{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "TC{$n}", 'status' => $status, 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1', 'Grade 2']]);

        return [$user, $teacher, $user->createToken('mobile')->plainTextToken];
    }

    private function klass(Teacher $t, string $name = 'Sampaguita', ?string $year = null): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $t->id, 'name' => $name, 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => $year ?? SchoolClass::currentSchoolYear()]);
    }

    private function kid(?SchoolClass $c, string $first = 'Mia'): Learner
    {
        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $c?->id, 'first_name' => $first, 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Beginning', 'reading_rung' => 2,
        ]);
    }

    private function parentOf(Learner $kid): User
    {
        $u = User::create(['first_name' => 'Pam', 'last_name' => 'Cruz', 'email' => 'pam.tcd'.(++$this->seq).'@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $u->forceFill(['email_verified_at' => now()])->save();
        ParentAccount::create(['user_id' => $u->id])->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true]);

        return $u;
    }

    private function activity(Teacher $t): Activity
    {
        return Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'title' => 'Words', 'instructions' => 'Read.', 'passage_text' => 'cat dog', 'word_count' => 2, 'status' => 'Approved',
        ]);
    }

    // ------------------------------------------------------------------ what the screens are told

    public function test_the_class_and_learner_pages_say_what_may_be_done_and_what_deleting_would_change(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->klass($t);
        $mia = $this->kid($class, 'Mia');
        $this->kid($class, 'Ben');
        ActivityAssignment::create(['activity_id' => $this->activity($t)->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $t->id]);

        $this->api($token)->getJson("/api/teacher/classes/{$class->id}")->assertOk()
            ->assertJsonPath('canDelete', true)->assertJsonPath('deletion.learners', 2)->assertJsonPath('deletion.activities', 1);
        $this->api($token)->getJson("/api/teacher/classes/{$class->id}/learners/{$mia->id}")->assertOk()->assertJsonPath('canRemove', true)
            ->assertJsonPath('class.id', $class->id)->assertJsonPath('class.name', 'Sampaguita');
    }

    public function test_a_past_year_and_a_pending_teacher_are_told_they_cannot(): void
    {
        [, $t, $token] = $this->teacher();
        $old = $this->klass($t, 'Old class', '2020-2021');
        $mia = $this->kid($old, 'Mia');
        $this->api($token)->getJson("/api/teacher/classes/{$old->id}")->assertOk()->assertJsonPath('canDelete', false);
        $this->api($token)->getJson("/api/teacher/classes/{$old->id}/learners/{$mia->id}")->assertOk()->assertJsonPath('canRemove', false);

        [, $p, $pendingToken] = $this->teacher('Pending');
        $theirs = $this->klass($p);
        $ben = $this->kid($theirs, 'Ben');
        $this->api($pendingToken)->getJson("/api/teacher/classes/{$theirs->id}")->assertOk()->assertJsonPath('canDelete', false);
        $this->api($pendingToken)->getJson("/api/teacher/classes/{$theirs->id}/learners/{$ben->id}")->assertOk()->assertJsonPath('canRemove', false);
    }

    // ------------------------------------------------------------------ taking a learner out

    public function test_removing_a_learner_keeps_the_child_and_tells_the_parent(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->klass($t);
        $mia = $this->kid($class, 'Mia');
        $ben = $this->kid($class, 'Ben');
        $parent = $this->parentOf($mia);

        $this->api($token)->deleteJson("/api/teacher/classes/{$class->id}/learners/{$mia->id}")->assertOk()
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Mia Cruz was taken out of "Sampaguita"') && str_contains($m, 'stay with their family'));

        $this->assertNull($mia->fresh()->class_id);
        $this->assertNotNull(Learner::find($mia->id));
        $this->assertSame($class->id, $ben->fresh()->class_id);
        $this->assertSame(1, Notification::where('recipient_user_id', $parent->id)->where('type', Notification::TYPE_CLASS_LEFT)->count());

        $this->api($token)->getJson("/api/teacher/classes/{$class->id}")->assertOk()->assertJsonCount(1, 'learners');
        // A second try finds the child no longer there.
        $this->api($token)->deleteJson("/api/teacher/classes/{$class->id}/learners/{$mia->id}")->assertNotFound();
    }

    public function test_removing_is_refused_for_the_wrong_class_wrong_learner_past_year_pending_parent_and_child(): void
    {
        [, $t, $token] = $this->teacher();
        [, $other, $otherToken] = $this->teacher();
        $class = $this->klass($t);
        $mia = $this->kid($class, 'Mia');
        $theirs = $this->klass($other, 'Theirs');
        $ben = $this->kid($theirs, 'Ben');
        $free = $this->kid(null, 'Zed');
        $old = $this->klass($t, 'Old class', '2020-2021');
        $past = $this->kid($old, 'Past');

        $this->api($otherToken)->deleteJson("/api/teacher/classes/{$class->id}/learners/{$mia->id}")->assertNotFound();
        $this->api($token)->deleteJson("/api/teacher/classes/{$class->id}/learners/{$ben->id}")->assertNotFound();
        $this->api($token)->deleteJson("/api/teacher/classes/{$class->id}/learners/{$free->id}")->assertNotFound();
        $this->api($token)->deleteJson("/api/teacher/classes/{$old->id}/learners/{$past->id}")->assertForbidden();

        [, $p, $pendingToken] = $this->teacher('Pending');
        $pc = $this->klass($p, 'Pending class');
        $pk = $this->kid($pc, 'Pip');
        $this->api($pendingToken)->deleteJson("/api/teacher/classes/{$pc->id}/learners/{$pk->id}")->assertForbidden();

        $parentToken = $this->parentOf($this->kid(null, 'Kid'))->createToken('m')->plainTextToken;
        $this->api($parentToken)->deleteJson("/api/teacher/classes/{$class->id}/learners/{$mia->id}")->assertForbidden();
        $this->api($mia->createToken('m')->plainTextToken)->deleteJson("/api/teacher/classes/{$class->id}/learners/{$mia->id}")->assertForbidden();

        foreach ([[$mia, $class], [$ben, $theirs], [$past, $old], [$pk, $pc]] as [$kid, $c]) {
            $this->assertSame($c->id, $kid->fresh()->class_id, 'nothing was removed');
        }
    }

    // ------------------------------------------------------------------ deleting a class

    public function test_an_empty_class_is_deleted_at_once(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->klass($t);

        $this->api($token)->deleteJson("/api/teacher/classes/{$class->id}")->assertOk()->assertJsonPath('message', 'Class "Sampaguita" deleted.')->assertJsonPath('released', 0);
        $this->assertNull(SchoolClass::find($class->id));
    }

    public function test_a_class_with_learners_needs_its_name_and_then_releases_them(): void
    {
        [, $t, $token] = $this->teacher();
        $class = $this->klass($t);
        $mia = $this->kid($class, 'Mia');
        $ben = $this->kid($class, 'Ben');
        $parent = $this->parentOf($mia);
        ActivityAssignment::create(['activity_id' => $this->activity($t)->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $t->id]);

        $this->api($token)->deleteJson("/api/teacher/classes/{$class->id}")->assertStatus(422)->assertJsonValidationErrors('confirm_name');
        $this->api($token)->deleteJson("/api/teacher/classes/{$class->id}", ['confirm_name' => 'Ilang-Ilang'])->assertStatus(422)->assertJsonValidationErrors('confirm_name');
        $this->assertNotNull(SchoolClass::find($class->id), 'nothing happened yet');
        $this->assertSame($class->id, $mia->fresh()->class_id);

        $this->api($token)->deleteJson("/api/teacher/classes/{$class->id}", ['confirm_name' => ' SAMPAGUITA '])->assertOk()->assertJsonPath('released', 2)
            ->assertJsonPath('message', fn ($m) => str_contains($m, '2 learners were released, not deleted'));

        $this->assertNull(SchoolClass::find($class->id));
        $this->assertNull($mia->fresh()->class_id);
        $this->assertNull($ben->fresh()->class_id);
        $this->assertNotNull(Learner::find($mia->id));
        $this->assertSame(0, ActivityAssignment::where('class_id', $class->id)->count());
        $this->assertSame(1, Notification::where('recipient_user_id', $parent->id)->where('type', Notification::TYPE_CLASS_LEFT)->count());
    }

    public function test_deleting_is_refused_for_another_teachers_class_a_past_year_a_pending_teacher_a_parent_and_a_child(): void
    {
        [, $t, $token] = $this->teacher();
        [, , $otherToken] = $this->teacher();
        $class = $this->klass($t);
        $old = $this->klass($t, 'Old class', '2020-2021');

        // Nobody signed in: sent away before anything else (this goes first, because a token used once stays on the later calls).
        $this->deleteJson("/api/teacher/classes/{$class->id}")->assertUnauthorized();
        $this->api($otherToken)->deleteJson("/api/teacher/classes/{$class->id}")->assertNotFound();
        $this->api($token)->deleteJson("/api/teacher/classes/{$old->id}")->assertForbidden();

        [, $p, $pendingToken] = $this->teacher('Pending');
        $pc = $this->klass($p, 'Pending class');
        $this->api($pendingToken)->deleteJson("/api/teacher/classes/{$pc->id}")->assertForbidden();

        $mia = $this->kid($class, 'Mia');
        $parentToken = $this->parentOf($mia)->createToken('m')->plainTextToken;
        $this->api($parentToken)->deleteJson("/api/teacher/classes/{$class->id}", ['confirm_name' => 'Sampaguita'])->assertForbidden();
        $this->api($mia->createToken('m')->plainTextToken)->deleteJson("/api/teacher/classes/{$class->id}", ['confirm_name' => 'Sampaguita'])->assertForbidden();

        foreach ([$class, $old, $pc] as $c) {
            $this->assertNotNull(SchoolClass::find($c->id), 'nothing was deleted');
        }
        $this->assertSame($class->id, $mia->fresh()->class_id);
    }
}

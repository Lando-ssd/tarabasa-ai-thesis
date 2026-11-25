<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\ParentAccount;
use App\Models\PromotionRecord;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReadingProgression;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A teacher can take a learner out of a class and delete a class. Neither ever deletes a CHILD: the learner keeps the account, the readings
 * and the level, and the parents are told. A class that still has learners is deleted only when its name is typed. A class of a past school
 * year is a locked record. Another teacher's class and a Pending teacher are refused.
 */
class TeacherClassDeleteTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** @return array{0: User, 1: Teacher} */
    private function teacher(string $status = 'Active'): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "cd{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "D{$n}", 'status' => $status, 'free_generation_credits_remaining' => 2, 'grades_handled' => ['Grade 1', 'Grade 2']]);

        return [$user, $teacher];
    }

    private function klass(Teacher $t, string $name = 'Sampaguita', string $grade = 'Grade 1', ?string $year = null, ?string $tag = null): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $t->id, 'name' => $name, 'grade_level' => $grade, 'section' => 'A', 'group_tag' => $tag, 'school_year' => $year ?? SchoolClass::currentSchoolYear()]);
    }

    private function kid(?SchoolClass $c, string $first = 'Mia', string $grade = 'Grade 1'): Learner
    {
        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $c?->id, 'first_name' => $first, 'last_name' => 'Cruz', 'grade_level' => $grade,
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => ReadingProgression::levelForRung(2), 'reading_rung' => 2,
        ]);
    }

    private function activity(Teacher $t, string $title = 'Words'): Activity
    {
        return Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'title' => $title, 'instructions' => 'Read.', 'passage_text' => 'cat dog pig', 'word_count' => 3, 'status' => 'Approved',
        ]);
    }

    private function parentOf(Learner $kid, string $email = 'pam@example.com'): User
    {
        $u = User::create(['first_name' => 'Pam', 'last_name' => 'Cruz', 'email' => $email, 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $u->forceFill(['email_verified_at' => now()])->save();
        ParentAccount::create(['user_id' => $u->id])->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true]);

        return $u;
    }

    private function read(Learner $kid, Activity $a): ReadingSession
    {
        return ReadingSession::create([
            'learner_id' => $kid->id, 'activity_id' => $a->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 92,
            'level_before' => 'Beginning', 'level_after' => 'Beginning', 'rung_before' => 2, 'rung_after' => 2,
        ]);
    }

    // ------------------------------------------------------------------ taking a learner out of a class

    public function test_removing_a_learner_keeps_the_child_and_the_readings_and_tells_the_parent(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t);
        $mia = $this->kid($class, 'Mia');
        $ben = $this->kid($class, 'Ben');
        $parent = $this->parentOf($mia);
        $words = $this->activity($t);
        $this->read($mia, $words);
        ActivityAssignment::create(['activity_id' => $words->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $t->id]);
        $direct = ActivityAssignment::create(['activity_id' => $this->activity($t, 'Only Mia')->id, 'learner_id' => $mia->id, 'assigned_by_teacher_id' => $t->id]);

        $this->actingAs($user)->delete(route('teacher.classes.remove-learner', [$class, $mia]))
            ->assertRedirect(route('teacher.classes.index', ['school_year' => $class->school_year, 'open' => $class->id, 'tab' => 'learners']))
            ->assertSessionHas('status');

        $this->assertNull($mia->fresh()->class_id, 'the learner has no class now');
        $this->assertSame($class->id, $ben->fresh()->class_id, 'the others stay');
        $this->assertNotNull(Learner::find($mia->id), 'the child is not deleted');
        $this->assertSame(1, ReadingSession::where('learner_id', $mia->id)->count(), 'the readings stay with the child');
        $this->assertNotNull(SchoolClass::find($class->id), 'the class stays');
        $this->assertSame(1, ActivityAssignment::where('class_id', $class->id)->count(), 'what the class was given stays with the class');

        $reaching = ActivityAssignment::reachingLearner($mia->fresh())->pluck('id');
        $this->assertSame([$direct->id], $reaching->all(), 'only what was given to the child directly still reaches them');

        $note = Notification::where('recipient_user_id', $parent->id)->where('type', Notification::TYPE_CLASS_LEFT)->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('Mia was taken out of the class "Sampaguita"', $note->message);
        $this->assertStringContainsString('not changed', $note->message);
        $this->assertSame(0, Notification::where('recipient_user_id', $user->id)->count(), 'the teacher who did it is not told');
    }

    public function test_a_removed_learner_can_be_added_again_with_their_code_and_the_old_class_slot_is_free(): void
    {
        [$user, $t] = $this->teacher();
        $first = $this->klass($t, 'Sampaguita');
        $second = $this->klass($t, 'Ilang-Ilang');
        $mia = $this->kid($first, 'Mia');

        $this->actingAs($user)->delete(route('teacher.classes.remove-learner', [$first, $mia]));
        $this->assertNull($mia->fresh()->class_id);

        $this->post(route('teacher.classes.join-learner', $second), ['learner_code' => $mia->learner_code])->assertSessionHasNoErrors();
        $this->assertSame($second->id, $mia->fresh()->class_id);
    }

    public function test_a_learner_who_is_not_in_that_class_cannot_be_removed_through_it(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t, 'Sampaguita');
        $other = $this->klass($t, 'Ilang-Ilang');
        $stranger = $this->kid($other, 'Ben');
        $free = $this->kid(null, 'Zed');

        $this->actingAs($user)->delete(route('teacher.classes.remove-learner', [$class, $stranger]))->assertNotFound();
        $this->delete(route('teacher.classes.remove-learner', [$class, $free]))->assertNotFound();
        $this->assertSame($other->id, $stranger->fresh()->class_id);
    }

    public function test_another_teachers_class_cannot_lose_a_learner_and_a_pending_teacher_is_stopped(): void
    {
        [$owner, $t] = $this->teacher();
        [$intruder] = $this->teacher();
        [$pending, $p] = $this->teacher('Pending');
        $class = $this->klass($t);
        $mia = $this->kid($class, 'Mia');
        $theirs = $this->klass($p, 'Pending class');
        $ben = $this->kid($theirs, 'Ben');

        $this->actingAs($intruder)->delete(route('teacher.classes.remove-learner', [$class, $mia]))->assertForbidden();
        $this->assertSame($class->id, $mia->fresh()->class_id);

        $this->actingAs($pending)->from('/teacher/classes')->delete(route('teacher.classes.remove-learner', [$theirs, $ben]))->assertSessionHas('classError');
        $this->assertSame($theirs->id, $ben->fresh()->class_id, 'a Pending teacher cannot touch real students');
    }

    public function test_a_class_of_a_past_school_year_cannot_lose_a_learner(): void
    {
        [$user, $t] = $this->teacher();
        $old = $this->klass($t, 'Old class', 'Grade 1', '2020-2021');
        $mia = $this->kid($old, 'Mia');

        $this->actingAs($user)->delete(route('teacher.classes.remove-learner', [$old, $mia]))->assertForbidden();
        $this->assertSame($old->id, $mia->fresh()->class_id);
    }

    // ------------------------------------------------------------------ deleting a class

    public function test_an_empty_class_is_deleted_without_typing_anything_and_its_assignments_go_with_it(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t);
        $keep = $this->klass($t, 'Ilang-Ilang');
        $words = $this->activity($t);
        ActivityAssignment::create(['activity_id' => $words->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $t->id]);
        ActivityAssignment::create(['activity_id' => $words->id, 'class_id' => $class->id, 'reading_band' => 'support', 'assigned_by_teacher_id' => $t->id]);
        $other = ActivityAssignment::create(['activity_id' => $words->id, 'class_id' => $keep->id, 'assigned_by_teacher_id' => $t->id]);

        $this->actingAs($user)->delete(route('teacher.classes.destroy', $class))
            ->assertRedirect(route('teacher.classes.index', ['school_year' => $class->school_year]))
            ->assertSessionHas('status', 'Class "Sampaguita" deleted.');

        $this->assertNull(SchoolClass::find($class->id));
        $this->assertSame(0, ActivityAssignment::where('class_id', $class->id)->count());
        $this->assertNotNull(ActivityAssignment::find($other->id), 'another class keeps its own assignments');
        $this->assertNotNull(SchoolClass::find($keep->id));
        $this->assertNotNull(Activity::find($words->id), 'the activity itself is never deleted');
    }

    public function test_a_class_with_learners_is_not_deleted_until_its_name_is_typed(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t);
        $mia = $this->kid($class, 'Mia');
        $ben = $this->kid($class, 'Ben');

        $this->actingAs($user)->from('/teacher/classes')->delete(route('teacher.classes.destroy', $class))->assertSessionHasErrors('confirm_name');
        $this->assertStringContainsString('release 2 learners', session('errors')->first('confirm_name'));
        $this->from('/teacher/classes')->delete(route('teacher.classes.destroy', $class), ['confirm_name' => 'Ilang-Ilang'])->assertSessionHasErrors('confirm_name');
        $this->from('/teacher/classes')->delete(route('teacher.classes.destroy', $class), ['confirm_name' => '   '])->assertSessionHasErrors('confirm_name');

        $this->assertNotNull(SchoolClass::find($class->id), 'nothing happened');
        $this->assertSame($class->id, $mia->fresh()->class_id);
        $this->assertSame($class->id, $ben->fresh()->class_id);
    }

    public function test_the_forced_delete_releases_every_learner_keeps_the_children_and_tells_the_parents(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t, 'Sampaguita', 'Grade 1', null, 'phonics');
        $mia = $this->kid($class, 'Mia');
        $ben = $this->kid($class, 'Ben');
        $miaParent = $this->parentOf($mia, 'pam@example.com');
        $benParent = $this->parentOf($ben, 'bob@example.com');
        $words = $this->activity($t);
        $this->read($mia, $words);
        ActivityAssignment::create(['activity_id' => $words->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $t->id]);
        $focus = ActivityAssignment::create(['activity_id' => $words->id, 'group_tag' => 'phonics', 'assigned_by_teacher_id' => $t->id]);
        $direct = ActivityAssignment::create(['activity_id' => $words->id, 'learner_id' => $ben->id, 'assigned_by_teacher_id' => $t->id]);
        $record = PromotionRecord::create(['learner_id' => $mia->id, 'released_by_teacher_id' => $t->id, 'next_grade' => 'Grade 2', 'status' => 'Pending', 'released_from_class_id' => $class->id]);

        $this->actingAs($user)->delete(route('teacher.classes.destroy', $class), ['confirm_name' => '  sampaguita '])
            ->assertRedirect(route('teacher.classes.index', ['school_year' => $class->school_year]))
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Class "Sampaguita" deleted. 2 learners were released, not deleted'));

        $this->assertNull(SchoolClass::find($class->id));
        $this->assertNotNull(Learner::find($mia->id));
        $this->assertNotNull(Learner::find($ben->id));
        $this->assertNull($mia->fresh()->class_id);
        $this->assertNull($ben->fresh()->class_id);
        $this->assertSame(1, ReadingSession::where('learner_id', $mia->id)->count(), 'a reading is kept with the child, not the class');
        $this->assertSame(0, ActivityAssignment::where('class_id', $class->id)->count());
        $this->assertNotNull(ActivityAssignment::find($focus->id), 'a focus group is shared with other classes, so it stays');
        $this->assertNotNull(ActivityAssignment::find($direct->id), 'what was given to a child directly stays');
        $this->assertNotNull(PromotionRecord::find($record->id), 'the promotion history stays');
        $this->assertNull($record->fresh()->released_from_class_id, 'and only forgets the class');

        foreach ([[$miaParent, 'Mia'], [$benParent, 'Ben']] as [$p, $first]) {
            $note = Notification::where('recipient_user_id', $p->id)->where('type', Notification::TYPE_CLASS_LEFT)->first();
            $this->assertNotNull($note, "{$first}'s parent is told");
            $this->assertStringContainsString("{$first} is no longer in the class \"Sampaguita\"", $note->message);
        }
        $this->assertSame(0, Notification::where('recipient_user_id', $user->id)->count());
    }

    public function test_a_class_of_a_past_school_year_is_never_deleted_not_even_when_empty(): void
    {
        [$user, $t] = $this->teacher();
        $old = $this->klass($t, 'Old class', 'Grade 1', '2020-2021');

        $this->actingAs($user)->delete(route('teacher.classes.destroy', $old))->assertForbidden();
        $this->assertNotNull(SchoolClass::find($old->id));
    }

    public function test_another_teachers_class_cannot_be_deleted_and_a_pending_teacher_is_stopped(): void
    {
        [$owner, $t] = $this->teacher();
        [$intruder] = $this->teacher();
        [$pending, $p] = $this->teacher('Pending');
        $class = $this->klass($t);
        $theirs = $this->klass($p, 'Pending class');

        $this->actingAs($intruder)->delete(route('teacher.classes.destroy', $class))->assertForbidden();
        $this->assertNotNull(SchoolClass::find($class->id));

        $this->actingAs($pending)->from('/teacher/classes')->delete(route('teacher.classes.destroy', $theirs))->assertSessionHas('classError');
        $this->assertNotNull(SchoolClass::find($theirs->id));
    }

    public function test_a_child_cannot_be_deleted_by_a_teacher_through_this(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t);
        $mia = $this->kid($class, 'Mia');

        $this->actingAs($user)->delete(route('teacher.classes.destroy', $class), ['confirm_name' => 'Sampaguita']);

        $this->assertNotNull(Learner::find($mia->id), 'the child, the PIN and the code are all still there');
        $this->assertSame(1, Learner::where('learner_code', $mia->learner_code)->count());
    }

    // ------------------------------------------------------------------ what the window shows

    public function test_the_class_window_offers_the_trash_buttons_only_where_they_work(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t, 'Sampaguita');
        $empty = $this->klass($t, 'Empty room');
        $mia = $this->kid($class, 'Mia');
        $old = $this->klass($t, 'Old class', 'Grade 1', '2020-2021');
        $this->kid($old, 'Past');

        $html = $this->actingAs($user)->get(route('teacher.classes.index'))->assertOk()->getContent();
        $this->assertStringContainsString('action="'.route('teacher.classes.destroy', $class).'"', $html);
        $this->assertStringContainsString('action="'.route('teacher.classes.destroy', $empty).'"', $html);
        $this->assertStringContainsString('action="'.route('teacher.classes.remove-learner', [$class, $mia]).'"', $html);
        $this->assertStringContainsString('name="confirm_name"', $html, 'a class with learners asks for the name');
        $this->assertSame(1, substr_count($html, 'name="confirm_name"'), 'an empty class does not');

        $past = $this->get(route('teacher.classes.index', ['school_year' => '2020-2021']))->assertOk()->getContent();
        $this->assertStringNotContainsString('action="'.route('teacher.classes.destroy', $old).'"', $past, 'a past year is read only');
        $this->assertStringNotContainsString('name="confirm_name"', $past);
    }

    public function test_the_parent_can_read_the_class_left_notice_and_it_counts_as_routine(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t, 'Sampaguita');
        $mia = $this->kid($class, 'Mia');
        $parent = $this->parentOf($mia);

        $this->actingAs($user)->delete(route('teacher.classes.remove-learner', [$class, $mia]));

        $this->actingAs($parent);
        $this->get(route('parent.notifications.index'))->assertOk()->assertSee('Mia was taken out of the class');
        $this->get(route('parent.notifications.index', ['filter' => 'routine']))->assertOk()->assertSee('Mia was taken out of the class');
        $this->get(route('parent.notifications.index', ['filter' => 'attention']))->assertOk()->assertDontSee('Mia was taken out of the class');
    }

    public function test_a_wrong_name_reopens_the_delete_view_with_the_message(): void
    {
        [$user, $t] = $this->teacher();
        $class = $this->klass($t);
        $this->kid($class, 'Mia');

        $html = $this->actingAs($user)->followingRedirects()->from(route('teacher.classes.index'))
            ->delete(route('teacher.classes.destroy', $class), ['confirm_name' => 'nope', 'form' => 'class', 'target_class_id' => $class->id, 'view' => 'delete'])
            ->assertOk()->getContent();

        $this->assertStringContainsString('data-autoview="delete"', $html);
        $this->assertStringContainsString('Type the class name exactly as it is written, Sampaguita', $html);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\PromotionRecord;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ActivitySuggestions;
use App\Support\ActivityFit;
use App\Support\GradeMatch;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A grade is only for its own grade, with no exception: a Grade 1 class only gets Grade 1 activities and a Grade 2 class only Grade 2, even
 * when the teacher handles several grades. These check every way an activity can reach a class, a group or a learner (the activity window,
 * the class window, the alerts, the phone API), what is suggested, what a teacher may write, and who may claim a promoted learner.
 */
class GradeMatchTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    /** @return array{0: User, 1: Teacher} */
    private function teacher(array $grades = ['Grade 1']): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Elena', 'last_name' => "Reyes{$n}", 'email' => "grade{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();

        return [$user, Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "G{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => 2, 'grades_handled' => $grades])];
    }

    private function klass(Teacher $t, string $grade, string $name, ?string $tag = null): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $t->id, 'name' => $name, 'grade_level' => $grade, 'section' => 'A', 'group_tag' => $tag, 'school_year' => SchoolClass::currentSchoolYear()]);
    }

    private function kid(?SchoolClass $c, string $grade, string $name = 'Mia'): Learner
    {
        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'class_id' => $c?->id, 'first_name' => $name.++$this->seq, 'last_name' => 'Cruz', 'grade_level' => $grade,
            'pin' => Hash::make('2580'), 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'reading_rung' => 3,
        ]);
    }

    private function activity(Teacher $t, string $grade, string $title = 'Words', array $o = []): Activity
    {
        return Activity::create($o + [
            'created_by_teacher_id' => $t->id, 'grade_level' => $grade, 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'title' => $title, 'instructions' => 'Read.', 'passage_text' => 'cat dog pig hen', 'word_count' => 4, 'status' => 'Approved',
        ]);
    }

    private function as(User $u): self
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAs($u);
    }

    // ------------------------------------------------------------------ giving an activity

    public function test_a_grade_2_activity_cannot_go_to_a_grade_1_class_even_for_a_teacher_who_handles_both_grades(): void
    {
        [$u, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $g1 = $this->klass($t, 'Grade 1', 'Sampaguita');
        $g2 = $this->klass($t, 'Grade 2', 'Ilang-Ilang');
        $act = $this->activity($t, 'Grade 2', 'Grade two words');

        $this->as($u)->from('/x')->post(route('teacher.activities.assign', $act), ['assign_class_id' => $g1->id])->assertSessionHasErrors('assign_target');
        $this->assertStringContainsString('Grade 2 activity and Sampaguita is a Grade 1 class', session('errors')->first('assign_target'));
        $this->assertSame(0, ActivityAssignment::count());

        $this->post(route('teacher.activities.assign', $act), ['assign_class_id' => $g2->id])->assertSessionHasNoErrors();
        $this->assertSame(1, ActivityAssignment::where('class_id', $g2->id)->count());
    }

    public function test_the_class_window_route_refuses_another_grade_the_same_way(): void
    {
        [$u, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $g1 = $this->klass($t, 'Grade 1', 'Sampaguita');
        $act = $this->activity($t, 'Grade 2');

        $this->as($u)->from('/x')->post(route('teacher.classes.assign-activity', $g1), ['activity_id' => $act->id])->assertSessionHasErrors('activity_id');
        $this->assertSame(0, ActivityAssignment::count());

        $own = $this->activity($t, 'Grade 1', 'Own grade');
        $this->post(route('teacher.classes.assign-activity', $g1), ['activity_id' => $own->id])->assertSessionHasNoErrors();
        $this->assertSame(1, ActivityAssignment::count());
    }

    public function test_a_learner_only_gets_an_activity_of_their_own_grade(): void
    {
        [$u, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $g1 = $this->klass($t, 'Grade 1', 'Sampaguita');
        $kid = $this->kid($g1, 'Grade 1');
        $act = $this->activity($t, 'Grade 2');

        $this->as($u)->from('/x')->post(route('teacher.activities.assign', $act), ['assign_learner_id' => $kid->id])->assertSessionHasErrors('assign_target');
        $this->assertStringContainsString("{$kid->first_name} is in Grade 1", session('errors')->first('assign_target'));
        $this->assertSame(0, ActivityAssignment::count());
    }

    public function test_a_group_tag_that_reaches_a_class_of_another_grade_cannot_be_used(): void
    {
        [$u, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $g1 = $this->klass($t, 'Grade 1', 'Sampaguita', 'phonics');
        $g2 = $this->klass($t, 'Grade 2', 'Ilang-Ilang', 'phonics');
        $this->kid($g1, 'Grade 1');
        $act = $this->activity($t, 'Grade 1');

        $this->as($u)->from('/x')->post(route('teacher.activities.assign', $act), ['assign_group_tag' => 'phonics'])->assertSessionHasErrors('assign_target');
        $note = session('errors')->first('assign_target');
        $this->assertStringContainsString('Ilang-Ilang (Grade 2)', $note);
        $this->assertStringContainsString('keep each group tag to one grade', $note);
        $this->assertSame(0, ActivityAssignment::count());

        // The same tag on classes of ONE grade is fine.
        $g2->update(['group_tag' => null]);
        $this->post(route('teacher.activities.assign', $act), ['assign_group_tag' => 'phonics'])->assertSessionHasNoErrors();
        $this->assertSame(1, ActivityAssignment::count());
    }

    public function test_an_older_class_that_still_holds_a_learner_of_another_grade_is_blocked_until_that_learner_is_moved(): void
    {
        [$u, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $g1 = $this->klass($t, 'Grade 1', 'Sampaguita');
        $odd = $this->kid($g1, 'Grade 2', 'Odd');   // placed before the one-grade rule existed
        $act = $this->activity($t, 'Grade 1');

        $this->as($u)->from('/x')->post(route('teacher.activities.assign', $act), ['assign_class_id' => $g1->id])->assertSessionHasErrors('assign_target');
        $this->assertStringContainsString("{$odd->first_name} (Grade 2)", session('errors')->first('assign_target'));
        $this->assertStringContainsString('Move them to a class of their own grade first', session('errors')->first('assign_target'));
    }

    public function test_the_alert_one_click_assign_refuses_another_grade(): void
    {
        [$u, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $g1 = $this->klass($t, 'Grade 1', 'Sampaguita');
        $kid = $this->kid($g1, 'Grade 1');
        $other = $this->activity($t, 'Grade 2');

        $this->as($u)->from('/x')->post(route('teacher.alerts.assign', $kid), ['kind' => 'support', 'activity_id' => $other->id])->assertSessionHasErrors('activity_id');
        $this->assertSame(0, ActivityAssignment::count());
    }

    // ------------------------------------------------------------------ what the screens say

    public function test_the_activity_window_says_one_grade_only_and_tags_the_other_grades(): void
    {
        [$u, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $this->klass($t, 'Grade 1', 'Sampaguita');
        $this->klass($t, 'Grade 2', 'Ilang-Ilang');
        $act = $this->activity($t, 'Grade 2');

        $this->as($u)->get(route('teacher.activities.window', $act))->assertOk()
            ->assertSee('One grade only.')
            ->assertSee('This is a Grade 2 activity, so it can only go to Grade 2 classes')
            ->assertSee('Other grade');
    }

    public function test_the_phone_api_marks_a_class_of_another_grade_as_blocked_for_the_grade(): void
    {
        [$u, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $g1 = $this->klass($t, 'Grade 1', 'Sampaguita');
        $g2 = $this->klass($t, 'Grade 2', 'Ilang-Ilang');
        $act = $this->activity($t, 'Grade 2');
        $token = $u->createToken('m')->plainTextToken;

        $this->app['auth']->forgetGuards();
        $classes = collect($this->withToken($token)->getJson("/api/teacher/activities/{$act->id}")->assertOk()->json('targets.classes'))->keyBy('id');

        $this->assertSame(['blocked', 'grade'], [$classes[$g1->id]['fit']['verdict'], $classes[$g1->id]['fit']['reason']]);
        $this->assertNotSame('blocked', $classes[$g2->id]['fit']['verdict']);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson("/api/teacher/activities/{$act->id}/assign", ['assign_class_id' => $g1->id])->assertStatus(422);
        $this->assertSame(0, ActivityAssignment::count());
    }

    public function test_an_activity_given_before_the_rule_is_flagged_as_another_grade_in_the_class_window(): void
    {
        [$u, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $g1 = $this->klass($t, 'Grade 1', 'Sampaguita');
        $this->kid($g1, 'Grade 1');
        $old = $this->activity($t, 'Grade 2', 'Given long ago');
        ActivityAssignment::create(['activity_id' => $old->id, 'class_id' => $g1->id, 'assigned_by_teacher_id' => $t->id]);

        $this->as($u)->get(route('teacher.classes.index'))->assertOk()
            ->assertSee('Given long ago')
            ->assertSee('Other grade');
    }

    // ------------------------------------------------------------------ suggestions

    public function test_suggestions_never_offer_another_grade_even_when_there_is_nothing_else(): void
    {
        [, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $g1 = $this->klass($t, 'Grade 1', 'Sampaguita');
        $kid = $this->kid($g1, 'Grade 1');
        $this->activity($t, 'Grade 2', 'Only a grade two one');

        $suggestions = app(ActivitySuggestions::class);

        $this->assertNull($suggestions->forLearner($t, $kid->fresh(), 'Easy'), 'a learner is never handed another grade');
        $this->assertCount(0, $suggestions->forGroup($t, $g1->load('learners'), 'support', collect([$kid->fresh()])), 'nor a reading group');
        $this->assertCount(0, $suggestions->starterForClass($t, $g1), 'nor a class with no readings yet');

        // (a fresh service: the Teacher's approved activities are read once for each one)
        $own = $this->activity($t, 'Grade 1', 'Own grade');
        $this->assertSame($own->id, app(ActivitySuggestions::class)->forLearner($t, $kid->fresh(), 'Easy')['activity']->id);
    }

    // ------------------------------------------------------------------ what a teacher may write, and claim

    public function test_a_teacher_who_handles_one_grade_cannot_write_an_activity_for_another(): void
    {
        [$u, $t] = $this->teacher(['Grade 1']);

        $this->as($u)->from('/x')->post(route('teacher.activities.generate'), [
            'grade_level' => 'Grade 2', 'competency' => 'foundational_reading', 'activity_type' => 'word_reading', 'levels' => ['Easy' => 1],
        ])->assertSessionHasErrors('grade_level');
        $this->assertStringContainsString('You handle Grade 1 only', session('errors')->first('grade_level'));
        $this->assertSame(2, $t->fresh()->free_generation_credits_remaining, 'nothing was charged');
    }

    public function test_a_teacher_who_no_longer_handles_a_grade_cannot_claim_a_promoted_learner_into_an_old_class_of_it(): void
    {
        [$u, $t] = $this->teacher(['Grade 1']);
        [, $other] = $this->teacher(['Grade 1']);
        $oldG2 = $this->klass($t, 'Grade 2', 'Old Grade 2');   // from when this teacher handled both grades
        $kid = $this->kid(null, 'Grade 1');
        $record = PromotionRecord::create(['learner_id' => $kid->id, 'released_by_teacher_id' => $other->id, 'next_grade' => 'Grade 2', 'status' => 'Pending']);

        $this->as($u)->post(route('teacher.promotions.claim', $record), ['class_id' => $oldG2->id])->assertForbidden();
        $this->assertNull($kid->fresh()->class_id);
        $this->assertSame('Pending', $record->fresh()->status);

        $page = $this->get(route('teacher.promotions.index', ['tab' => 'claim']))->assertOk();
        $page->assertSee('You do not handle Grade 2');

        // Handling the grade again makes it claimable.
        $t->update(['grades_handled' => ['Grade 1', 'Grade 2']]);
        $this->as($u->fresh())->post(route('teacher.promotions.claim', $record), ['class_id' => $oldG2->id])->assertRedirect();
        $this->assertSame($oldG2->id, $kid->fresh()->class_id);
    }

    public function test_the_phone_promotions_list_says_why_a_claim_is_not_possible(): void
    {
        [$u, $t] = $this->teacher(['Grade 1']);
        [, $other] = $this->teacher(['Grade 1']);
        $this->klass($t, 'Grade 2', 'Old Grade 2');
        $kid = $this->kid(null, 'Grade 1');
        PromotionRecord::create(['learner_id' => $kid->id, 'released_by_teacher_id' => $other->id, 'next_grade' => 'Grade 2', 'status' => 'Pending']);

        $this->app['auth']->forgetGuards();
        $json = $this->withToken($u->createToken('m')->plainTextToken)->getJson('/api/teacher/promotions')->assertOk();

        $this->assertSame([], $json->json('claim.0.classes'));
        $this->assertStringContainsString('You do not handle Grade 2', $json->json('claim.0.note'));
        $this->assertSame(0, $json->json('claimable'));
    }

    // ------------------------------------------------------------------ the rule itself

    public function test_the_rule_never_turns_away_a_missing_grade_and_names_what_to_do_instead(): void
    {
        [, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $g1 = $this->klass($t, 'Grade 1', 'Sampaguita');
        $noGrade = $this->kid($g1, 'Grade 1');
        $noGrade->grade_level = null;
        $act = $this->activity($t, 'Grade 2');

        $this->assertFalse(GradeMatch::differs(null, 'Grade 1'));
        $this->assertFalse(GradeMatch::differs('Grade 2', ''));
        $this->assertNotSame(ActivityFit::BLOCKED, GradeMatch::forLearner($act, $noGrade)['reason'] ?? null);
        $this->assertStringContainsString('Give it to one of your Grade 2 classes', GradeMatch::forAudience($act, [], 'Sampaguita', [$g1])['note']);
    }
}

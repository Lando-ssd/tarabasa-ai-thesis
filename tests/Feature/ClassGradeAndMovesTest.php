<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\PromotionRecord;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ReadingProgression;
use App\Services\TeacherAlerts;
use App\Support\LearnerCode;
use App\Support\LevelMoves;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A class holds ONE grade, with no exception (so a Grade 2 learner cannot be added to a Grade 1 class, however the code arrives, and a
 * teacher who handles several grades opens one class per grade). And what happens when a child's reading improves:
 * they stay in the class (a class is a grade) and move to the next reading group, and the teacher is told.
 */
class ClassGradeAndMovesTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private ?Activity $activity = null;

    /** @return array{0: User, 1: Teacher} */
    private function teacher(array $grades): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "cg{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "G{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => 2, 'grades_handled' => $grades]);

        return [$user, $teacher];
    }

    private function klass(Teacher $t, string $grade, string $name = 'Class'): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $t->id, 'name' => $name, 'grade_level' => $grade, 'section' => 'A', 'group_tag' => null, 'school_year' => SchoolClass::currentSchoolYear()]);
    }

    private function kid(?SchoolClass $c, string $grade, int $rung = 1, array $o = []): Learner
    {
        $n = ++$this->seq;

        return Learner::create($o + [
            'learner_code' => LearnerCode::generate(), 'class_id' => $c?->id, 'first_name' => "Kid{$n}", 'last_name' => 'Cruz', 'grade_level' => $grade,
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => ReadingProgression::levelForRung($rung), 'reading_rung' => $rung,
        ]);
    }

    private function read(Learner $kid, Teacher $t, int $before, int $after, int $daysAgo = 1): ReadingSession
    {

        $activity = $this->activity ??= Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'title' => 'Words', 'instructions' => 'Read.', 'passage_text' => 'cat dog pig hen cow bat', 'word_count' => 6, 'status' => 'Approved',
        ]);
        $row = ReadingSession::create([
            'learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 95,
            'level_before' => ReadingProgression::levelForRung($before), 'level_after' => ReadingProgression::levelForRung($after), 'rung_before' => $before, 'rung_after' => $after,
        ]);
        $row->timestamp = now()->subDays($daysAgo);
        $row->save();

        return $row;
    }

    // ------------------------------------------------------------------ one grade per class

    public function test_a_grade_2_learner_cannot_be_added_to_a_grade_1_class_by_a_teacher_who_handles_one_grade(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $class = $this->klass($t, 'Grade 1', 'Sampaguita');
        $second = $this->kid(null, 'Grade 2', 1, ['first_name' => 'Rosa']);
        $first = $this->kid(null, 'Grade 1', 1, ['first_name' => 'Lito']);
        $this->actingAs($user);
        $url = route('teacher.classes.join-learner', $class);

        $this->from('/x')->post($url, ['learner_code' => $second->learner_code])->assertSessionHasErrors('learner_code');
        $message = session('errors')->first('learner_code');
        $this->assertStringContainsString('Rosa is in Grade 2, and Sampaguita is a Grade 1 class', $message);
        $this->assertStringContainsString('A class holds one grade only', $message);
        $this->assertStringContainsString('a Grade 1 class is only for Grade 1 learners', $message);
        $this->assertStringContainsString('classes of different grades cannot be merged', $message);
        $this->assertStringContainsString('You do not handle Grade 2', $message);
        $this->assertNull($second->fresh()->class_id);

        $this->postJson($url, ['learner_code' => $second->learner_code])->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertNull($second->fresh()->class_id, 'the scanner gets the same answer');

        $this->post($url, ['learner_code' => $first->learner_code])->assertRedirect();
        $this->assertSame($class->id, $first->fresh()->class_id);
    }

    public function test_a_teacher_who_handles_several_grades_still_gets_one_class_per_grade_with_no_mixing(): void
    {
        [$user, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $g1 = $this->klass($t, 'Grade 1', 'One');
        $g2 = $this->klass($t, 'Grade 2', 'Two');
        $kid = $this->kid(null, 'Grade 2', 1, ['first_name' => 'Rosa']);
        $three = $this->kid(null, 'Grade 3', 1, ['first_name' => 'Tomas']);
        $this->actingAs($user);

        // Handling both grades does not let a Grade 2 learner into the Grade 1 class.
        $this->from('/x')->post(route('teacher.classes.join-learner', $g1), ['learner_code' => $kid->learner_code])->assertSessionHasErrors('learner_code');
        $this->assertStringContainsString('one of your Grade 2 classes, or open a new Grade 2 class', session('errors')->first('learner_code'));
        $this->assertNull($kid->fresh()->class_id);

        // The Grade 2 class takes them.
        $this->post(route('teacher.classes.join-learner', $g2), ['learner_code' => $kid->learner_code])->assertRedirect();
        $this->assertSame($g2->id, $kid->fresh()->class_id);

        // A grade the teacher does not handle is refused everywhere.
        $this->from('/x')->post(route('teacher.classes.join-learner', $g1), ['learner_code' => $three->learner_code])->assertSessionHasErrors('learner_code');
        $this->assertStringContainsString('You do not handle Grade 3', session('errors')->first('learner_code'));
    }

    public function test_class_creation_offers_only_the_grades_handled_and_there_is_no_way_to_make_a_mixed_class(): void
    {
        [$single, $ts] = $this->teacher(['Grade 1']);
        $year = SchoolClass::currentSchoolYear();
        $this->actingAs($single)->post(route('teacher.classes.store'), ['name' => 'Solo', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => $year, 'multigrade' => '1'])->assertRedirect();
        $this->assertFalse(array_key_exists('multigrade', SchoolClass::firstWhere('name', 'Solo')->getAttributes()), 'there is no multigrade setting any more');
        $html = $this->get(route('teacher.classes.index'))->getContent();
        $this->assertStringNotContainsString('name="multigrade"', $html);
        $this->assertStringNotContainsString('Grades cannot be merged in one class', $html, 'a one-grade teacher does not need the reminder');

        [$multi, $tm] = $this->teacher(['Grade 1', 'Grade 2']);
        $page = $this->actingAs($multi)->get(route('teacher.classes.index'))->assertOk()->getContent();
        $this->assertStringContainsString('One class, one grade.', $page);
        $this->assertStringContainsString('Grades cannot be merged in one class', $page);
        $this->assertStringNotContainsString('name="multigrade"', $page);
        $this->assertStringContainsString('<option >Grade 1</option>', str_replace('<option>', '<option >', $page));

        $this->post(route('teacher.classes.store'), ['name' => 'G1', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => $year])->assertRedirect();
        $this->post(route('teacher.classes.store'), ['name' => 'G2', 'grade_level' => 'Grade 2', 'section' => 'A', 'school_year' => $year])->assertRedirect();
        $this->from('/x')->post(route('teacher.classes.store'), ['name' => 'G3', 'grade_level' => 'Grade 3', 'section' => 'A', 'school_year' => $year])->assertSessionHasErrors('grade_level');
        $this->assertSame(['Grade 1', 'Grade 2'], SchoolClass::where('teacher_id', $tm->id)->orderBy('grade_level')->pluck('grade_level')->all());
    }

    public function test_a_classs_grade_cannot_change_under_learners_it_would_no_longer_take(): void
    {
        [$user, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $class = $this->klass($t, 'Grade 1', 'One');
        $kid = $this->kid($class, 'Grade 1', 1, ['first_name' => 'Lito']);
        $empty = $this->klass($t, 'Grade 1', 'Empty');
        $this->actingAs($user);
        $edit = fn (SchoolClass $c, array $o) => $this->from('/x')->put(route('teacher.classes.update', $c), $o + ['name' => $c->name, 'section' => 'A', 'grade_level' => $c->grade_level]);

        $edit($class, ['grade_level' => 'Grade 2'])->assertSessionHasErrors('grade_level');
        $this->assertStringContainsString('a Grade 2 class is only for Grade 2 learners', session('errors')->first('grade_level'));
        $this->assertSame('Grade 1', $class->fresh()->grade_level);

        $edit($empty, ['grade_level' => 'Grade 2'])->assertSessionHasNoErrors();
        $this->assertSame('Grade 2', $empty->fresh()->grade_level, 'an empty class can change grade');

        $edit($class, ['name' => 'Renamed'])->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $class->fresh()->name, 'renaming is always fine');
    }

    public function test_a_learner_can_only_be_moved_to_a_class_that_takes_their_grade(): void
    {
        [$user, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $from = $this->klass($t, 'Grade 1', 'FromOne');
        $wrong = $this->klass($t, 'Grade 2', 'WrongTwo');
        $right = $this->klass($t, 'Grade 1', 'RightOne');
        $kid = $this->kid($from, 'Grade 1', 1, ['first_name' => 'Lito']);
        $this->actingAs($user);

        $this->from('/x')->post(route('teacher.classes.move-learner', [$from, $kid]), ['to_class_id' => $wrong->id])->assertSessionHasErrors('to_class_id');
        $this->assertSame($from->id, $kid->fresh()->class_id);

        $html = $this->get(route('teacher.classes.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<option value="'.$right->id.'">RightOne/', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="'.$wrong->id.'">WrongTwo/', $html, 'a Grade 1 child is never offered a Grade 2 class');

        $this->post(route('teacher.classes.move-learner', [$from, $kid]), ['to_class_id' => $right->id])->assertRedirect();
        $this->assertSame($right->id, $kid->fresh()->class_id);
    }

    public function test_a_learner_already_in_the_wrong_grade_is_flagged_not_removed(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $class = $this->klass($t, 'Grade 1', 'Sampaguita');
        $this->kid($class, 'Grade 2', 1, ['first_name' => 'Rosa']);

        $html = $this->actingAs($user)->get(route('teacher.classes.index'))->assertOk()->getContent();

        $this->assertStringContainsString('One learner is in a different grade', $html);
        $this->assertStringContainsString('Grade 2, other grade', $html);
        $this->assertStringContainsString('Rosa is in Grade 2, and Sampaguita is a Grade 1 class', $html);
        $this->assertSame(1, Learner::where('class_id', $class->id)->count(), 'nobody is removed');
    }

    public function test_a_promoted_learner_can_only_be_claimed_into_a_class_of_the_grade_they_were_promoted_to(): void
    {
        [$user, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $g1 = $this->klass($t, 'Grade 1', 'SoloOne');
        $g2 = $this->klass($t, 'Grade 2', 'SoloTwo');
        $kid = $this->kid(null, 'Grade 1', 3, ['first_name' => 'Lito']);
        $record = PromotionRecord::create(['learner_id' => $kid->id, 'released_by_teacher_id' => $t->id, 'next_grade' => 'Grade 2', 'status' => 'Pending', 'released_from_class_id' => $g1->id]);
        $this->actingAs($user);

        $this->post(route('teacher.promotions.claim', $record), ['class_id' => $g1->id])->assertStatus(403);
        $this->assertNull($kid->fresh()->class_id);

        $this->post(route('teacher.promotions.claim', $record), ['class_id' => $g2->id])->assertRedirect();
        $this->assertSame([$g2->id, 'Grade 2'], [$kid->fresh()->class_id, $kid->fresh()->grade_level]);
    }

    public function test_narrowing_the_grades_in_profile_warns_and_blocks_adding_to_a_class_of_a_grade_no_longer_handled(): void
    {
        [$user, $t] = $this->teacher(['Grade 1', 'Grade 2']);
        $g2 = $this->klass($t, 'Grade 2', 'OldTwo');
        $kid = $this->kid(null, 'Grade 2', 1, ['first_name' => 'Rosa']);
        $this->actingAs($user);

        $this->put(route('profile.grades.update'), ['grades_mode' => 'single', 'grades_handled' => ['Grade 1']])
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Each class still holds one grade only') && str_contains($m, 'OldTwo (Grade 2)') && str_contains($m, 'cannot take new learners'));

        $this->from('/x')->post(route('teacher.classes.join-learner', $g2), ['learner_code' => $kid->learner_code])->assertSessionHasErrors('learner_code');
        $this->assertStringContainsString('You no longer handle Grade 2', session('errors')->first('learner_code'));
        $this->assertNull($kid->fresh()->class_id);

        // Going the other way (one grade to several) just adds options: classes stay one grade each.
        $this->put(route('profile.grades.update'), ['grades_mode' => 'multi', 'grades_handled' => ['Grade 1', 'Grade 2']])
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'Each class still holds one grade only') && ! str_contains($m, 'cannot take new learners'));
        $this->post(route('teacher.classes.join-learner', $g2), ['learner_code' => $kid->learner_code])->assertRedirect();
        $this->assertSame($g2->id, $kid->fresh()->class_id);
    }

    public function test_the_sign_up_and_profile_form_says_each_class_holds_one_grade(): void
    {
        $html = $this->get(route('register.teacher'))->assertOk()->getContent();

        $this->assertStringContainsString('each class holds one grade only', $html);
        $this->assertStringContainsString('Grade 1 class is only for Grade 1 learners', $html);
        $this->assertStringNotContainsString('multigrade', strtolower($html));
    }

    // ------------------------------------------------------------------ improving inside the same class

    public function test_a_move_up_into_the_next_reading_level_is_noticed_only_while_it_is_still_true_and_fresh(): void
    {
        [, $t] = $this->teacher(['Grade 1']);
        $class = $this->klass($t, 'Grade 1');

        $up = $this->kid($class, 'Grade 1', 3);                      // Frustration (rung 2) to Instructional (rung 3)
        $this->read($up, $t, 2, 3, 1);
        $move = LevelMoves::latestUp($up->readingSessions()->get(), $up);
        $this->assertSame(['frustration', 'instructional'], [$move['from'], $move['to']]);
        $this->assertSame(['Frustration', 'Instructional'], [$move['fromLabel'], $move['toLabel']]);

        $inside = $this->kid($class, 'Grade 1', 2);                  // Words to Short sentences: same level, not a new group
        $this->read($inside, $t, 1, 2, 1);
        $this->assertNull(LevelMoves::latestUp($inside->readingSessions()->get(), $inside));

        $old = $this->kid($class, 'Grade 1', 3);                     // too long ago to be news
        $this->read($old, $t, 2, 3, 30);
        $this->assertNull(LevelMoves::latestUp($old->readingSessions()->get(), $old));

        $slipped = $this->kid($class, 'Grade 1', 2);                 // moved up, then back down
        $this->read($slipped, $t, 2, 3, 3);
        $this->assertNull(LevelMoves::latestUp($slipped->readingSessions()->get(), $slipped));

        $nonReader = $this->kid($class, 'Grade 1', 1);               // Non-reader to Frustration is a new level too
        $this->read($nonReader, $t, 0, 1, 1);
        $this->assertSame('non', LevelMoves::latestUp($nonReader->readingSessions()->get(), $nonReader)['from']);
    }

    public function test_the_teacher_is_told_a_child_moved_up_and_that_they_stay_in_their_class(): void
    {
        [$user, $t] = $this->teacher(['Grade 1']);
        $class = $this->klass($t, 'Grade 1', 'Sampaguita');
        $mary = $this->kid($class, 'Grade 1', 3, ['first_name' => 'Mary', 'last_name' => 'Rose']);
        $this->read($mary, $t, 2, 3, 1);
        $this->actingAs($user);

        $alerts = app(TeacherAlerts::class)->forTeacher($t);
        $this->assertCount(1, $alerts);
        $this->assertSame('moved', $alerts->first()['variant']);

        $this->get(route('teacher.notifications.index'))->assertOk()
            ->assertSee('Mary Rose moved up to the Instructional level')
            ->assertSee('From Frustration to Instructional')
            ->assertSee('Mary stays in Sampaguita')
            ->assertSee('a class is a grade')
            ->assertSee('Moving up');

        $html = $this->get(route('teacher.classes.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Moved up to Instructional', $html, 'a tag under the name in the roster');
        $this->assertStringContainsString('Mary moved up to the Instructional level.', $html, 'and on the learner\'s own page');
        $this->assertSame($class->id, $mary->fresh()->class_id, 'improving never moves a child out of the class');

        // Mark handled hides it, like every other alert.
        app(TeacherAlerts::class)->markHandled($t, $mary, 'up');
        $this->assertCount(0, app(TeacherAlerts::class)->forTeacher($t));
    }

    public function test_each_ladder_step_belongs_to_one_reading_level(): void
    {
        $this->assertSame(['non', 'frustration', 'frustration', 'instructional', 'instructional', 'independent', 'independent'], array_map(fn ($r) => \App\Support\ReadingLevel::bandForRung($r), range(0, 6)));
    }

    public function test_the_notice_to_teacher_and_parent_names_the_new_level_only_when_the_level_changed(): void
    {
        [, $t] = $this->teacher(['Grade 1']);
        $class = $this->klass($t, 'Grade 1', 'Sampaguita');
        $mary = $this->kid($class, 'Grade 1', 3, ['first_name' => 'Mary']);
        $activity = Activity::create([
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'title' => 'Farm words', 'instructions' => 'Read.', 'passage_text' => 'cat dog', 'word_count' => 2, 'status' => 'Approved',
        ]);
        $session = ReadingSession::create(['learner_id' => $mary->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 95]);
        $notify = new \ReflectionMethod(\App\Services\LearnerReadingService::class, 'notifyForSession');
        $service = app(\App\Services\LearnerReadingService::class);

        $notify->invoke($service, $mary, $activity, $session, ['moved' => 'up', 'rungBefore' => 2, 'rungAfter' => 3, 'rungLabelAfter' => 'Sentences', 'stepAfter' => 'Sentence Reader']);
        $notify->invoke($service, $mary, $activity, $session, ['moved' => 'up', 'rungBefore' => 1, 'rungAfter' => 2, 'rungLabelAfter' => 'Short sentences', 'stepAfter' => 'Sentence Reader']);

        $messages = \App\Models\Notification::pluck('message');
        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'moved up to Sentences (Sentence Reader), now at the Instructional level.')));
        $this->assertTrue($messages->contains(fn ($m) => str_contains($m, 'moved up to Short sentences (Sentence Reader).')), 'a step inside the same level does not claim a new level');
        $this->assertFalse($messages->contains(fn ($m) => str_contains($m, 'Short sentences (Sentence Reader), now at')));
    }
}

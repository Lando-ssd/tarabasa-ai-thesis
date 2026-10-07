<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\PersonalWordBank;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\LearnerAuthService;
use App\Support\LearnerCode;
use App\Support\NotSure;
use App\Support\ReadingLevel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The October 2026 revision round, the Learner side and what it rests on: the one reading-level
 * language (Phil-IRI names for adults, a four step path for children), "not sure" words, the practice
 * stage before a real reading, reading groups reaching the right children, and the Home wording.
 */
class LearnerRevisionTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function teacher(): Teacher
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "t{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);

        return Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "E{$n}", 'status' => 'Active']);
    }

    private function klass(Teacher $t, array $o = []): SchoolClass
    {
        return SchoolClass::create($o + ['teacher_id' => $t->id, 'name' => 'Rizal', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
    }

    private function learner(array $o = []): Learner
    {
        $n = ++$this->seq;

        return Learner::create($o + [
            'learner_code' => LearnerCode::generate(), 'first_name' => "Kid{$n}", 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'reading_rung' => 3,
        ]);
    }

    private function activity(Teacher $t, array $o = []): Activity
    {
        return Activity::create($o + [
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'ai_difficulty_tier' => 'Easy', 'title' => 'Farm Words', 'instructions' => 'Read.',
            'passage_text' => 'cat dog pig hen cow bat rat fox', 'reference_text' => 'cat dog pig hen cow bat rat fox', 'word_count' => 8, 'status' => 'Approved',
        ]);
    }

    private function finishedCheck(Learner $kid, Activity $any): void
    {
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $any->id, 'session_type' => 'Diagnostic', 'initiated_by' => 'Teacher', 'accuracy_percent' => 80]);
    }

    /** A Reading-api /analyze answer for "cat dog pig hen cow bat rat fox" where the 2nd to last words went wrong. */
    private function analysis(array $overrides = []): array
    {
        $words = ['cat', 'dog', 'pig', 'hen', 'cow', 'bat', 'rat', 'fox'];
        $feedback = [];
        $stamps = [];
        foreach ($words as $i => $w) {
            $wrong = $overrides['wrong'][$i] ?? null;
            $feedback[] = $wrong ? ['reference' => $w, 'spoken' => $wrong[0], 'status' => 'substitution'] : ['reference' => $w, 'spoken' => $w, 'status' => 'correct'];
            $stamps[] = ['word' => $wrong[0] ?? $w, 'start' => $i, 'end' => $i + .5, 'confidence' => $wrong[1] ?? 1.0];
        }

        return [
            'accuracy' => ['accuracy_score' => $overrides['accuracy'] ?? 75.0, 'spoken_word_count' => 8, 'word_feedback' => $feedback, 'substitutions' => count($overrides['wrong'] ?? []), 'deletions' => 0, 'insertions' => 0],
            'speed' => ['wcpm' => 60, 'speed_score' => 70],
            'prosody' => ['prosody_score' => 60],
            'word_timestamps' => $stamps,
        ];
    }

    // ------------------------------------------------------------- the reading-level language

    public function test_one_level_language_for_adults_and_children(): void
    {
        $nonReader = $this->learner(['mastery_level' => 'Beginning', 'reading_rung' => 0]);
        $words = $this->learner(['mastery_level' => 'Beginning', 'reading_rung' => 1]);
        $sentences = $this->learner(['mastery_level' => 'Developing', 'reading_rung' => 3]);
        $stories = $this->learner(['mastery_level' => 'Proficient', 'reading_rung' => 6]);
        $old = $this->learner(['mastery_level' => 'Developing', 'reading_rung' => null]);
        $new = $this->learner(['mastery_level' => null, 'reading_rung' => null]);

        $this->assertSame('non', ReadingLevel::band($nonReader));
        $this->assertSame('Non-reader', ReadingLevel::forAdult($nonReader)['label']);
        $this->assertSame('frustration', ReadingLevel::band($words));
        $this->assertSame(3, ReadingLevel::step($this->learner(['mastery_level' => 'Beginning', 'reading_rung' => 2])), 'short sentences are the Sentence Reader step');
        $this->assertSame('instructional', ReadingLevel::band($sentences));
        $this->assertSame('independent', ReadingLevel::band($stories));
        $this->assertSame('unchecked', ReadingLevel::band($new));

        // The child's four steps: letters, words, sentences, stories.
        $this->assertSame([1, 2, 3, 4], [ReadingLevel::step($nonReader), ReadingLevel::step($words), ReadingLevel::step($sentences), ReadingLevel::step($stories)]);
        $this->assertSame('Letter Explorer', ReadingLevel::stepName($nonReader));
        $this->assertSame('Story Reader', ReadingLevel::stepName($stories));
        $this->assertSame('New Reader', ReadingLevel::stepName($new));

        // A child checked before the ladder existed is placed from the stored level.
        $this->assertSame(3, ReadingLevel::rung($old));
        $this->assertSame(3, ReadingLevel::step($old));

        // The stored level is untouched underneath the new words.
        $this->assertSame('Developing', $sentences->mastery_level);
    }

    public function test_one_reading_is_named_by_its_word_accuracy_with_the_phil_iri_bands(): void
    {
        $this->assertSame('independent', ReadingLevel::bandForAccuracy(97));
        $this->assertSame('instructional', ReadingLevel::bandForAccuracy(96.9));
        $this->assertSame('instructional', ReadingLevel::bandForAccuracy(90));
        $this->assertSame('frustration', ReadingLevel::bandForAccuracy(89.9));
        $this->assertNull(ReadingLevel::bandForAccuracy(null));
        $this->assertSame(['Easy', 'Easy', 'Medium', 'Hard'], array_map([ReadingLevel::class, 'tierForBand'], ['non', 'frustration', 'instructional', 'independent']));
    }

    public function test_groups_the_level_mix_and_the_level_check(): void
    {
        $maria = $this->learner(['first_name' => 'Maria', 'last_name' => 'Perez', 'mastery_level' => 'Beginning', 'reading_rung' => 0]);
        $jun = $this->learner(['first_name' => 'Jun', 'last_name' => 'Cruz', 'mastery_level' => 'Beginning', 'reading_rung' => 2]);
        $miguel = $this->learner(['first_name' => 'Miguel', 'last_name' => 'Manatad', 'mastery_level' => 'Proficient', 'reading_rung' => 6]);
        $learners = collect([$maria, $jun, $miguel]);

        $this->assertSame([1, 1, 0, 1], ReadingLevel::mix($learners));
        $groups = ReadingLevel::groups($learners);
        $this->assertSame(['Cruz', 'Perez'], $groups['support']->pluck('last_name')->all());
        $this->assertSame(['Manatad'], $groups['independent']->pluck('last_name')->all());
        $this->assertTrue($groups['instructional']->isEmpty());

        // Miguel reads two levels above the middle of this class; the teacher is only told.
        $flags = ReadingLevel::levelChecks($learners);
        $this->assertSame([$miguel->id], array_keys($flags));
        $this->assertSame('above', $flags[$miguel->id]['direction']);

        // Fewer than three checked learners: no flag at all.
        $this->assertSame([], ReadingLevel::levelChecks(collect([$maria, $miguel])));
    }

    // ------------------------------------------------------------- not sure

    public function test_a_faintly_heard_wrong_word_is_not_sure_and_left_out_of_the_accuracy(): void
    {
        config(['reading.unsure_confidence' => 0.5, 'reading.unsure_max_share' => 0.25]);

        // hen was heard as "hand" at 0.3 (not sure); bat as "bad" at 0.95 (confidently wrong); 6 right.
        $result = NotSure::apply($this->analysis(['accuracy' => 75.0, 'wrong' => [3 => ['hand', 0.3], 5 => ['bad', 0.95]]]));

        $feedback = $result['accuracy']['word_feedback'];
        $this->assertTrue($feedback[3]['unsure']);
        $this->assertArrayNotHasKey('unsure', $feedback[5]);
        $this->assertSame(1, $result['accuracy']['not_sure_count']);
        // 6 right of 8, one not counted: 6 of 7.
        $this->assertSame(round(6 / 7 * 100, 2), $result['accuracy']['accuracy_score']);
        $this->assertSame(75.0, $result['accuracy']['accuracy_score_raw']);
        $this->assertSame(['right' => 6, 'notSure' => 1, 'total' => 8], NotSure::counts($feedback));
    }

    public function test_not_sure_can_never_hide_a_reading_that_is_mostly_wrong(): void
    {
        config(['reading.unsure_confidence' => 0.5, 'reading.unsure_max_share' => 0.25]);

        // Five wrong words, all faint. At most a quarter of 8 words (2) may be not sure; the least confident first.
        $wrong = [0 => ['a', .1], 1 => ['b', .2], 2 => ['c', .3], 3 => ['d', .4], 4 => ['e', .45]];
        $result = NotSure::apply($this->analysis(['accuracy' => 37.5, 'wrong' => $wrong]));

        $this->assertSame(2, $result['accuracy']['not_sure_count']);
        $this->assertTrue($result['accuracy']['word_feedback'][0]['unsure']);
        $this->assertTrue($result['accuracy']['word_feedback'][1]['unsure']);
        $this->assertArrayNotHasKey('unsure', $result['accuracy']['word_feedback'][2]);
        // 3 right of 6 that count.
        $this->assertSame(50.0, $result['accuracy']['accuracy_score']);
    }

    public function test_a_skipped_word_or_a_missing_confidence_is_never_not_sure(): void
    {
        $r = $this->analysis(['accuracy' => 87.5]);
        $r['accuracy']['word_feedback'][2] = ['reference' => 'pig', 'spoken' => null, 'status' => 'deletion'];
        $this->assertSame($r, NotSure::apply($r), 'nothing to mark: the result is returned unchanged');

        $r2 = $this->analysis(['accuracy' => 87.5, 'wrong' => [3 => ['hand', 0.2]]]);
        unset($r2['word_timestamps']);
        $this->assertArrayNotHasKey('not_sure_count', NotSure::apply($r2)['accuracy']);
    }

    // ------------------------------------------------------------- reading groups reach the right children

    public function test_everything_given_to_a_child_is_shown_together_and_a_reading_group_only_reaches_its_own_learners(): void
    {
        $teacher = $this->teacher();
        $class = $this->klass($teacher, ['group_tag' => 'phonics']);
        $support = $this->learner(['class_id' => $class->id, 'mastery_level' => 'Beginning', 'reading_rung' => 1]);
        $independent = $this->learner(['class_id' => $class->id, 'mastery_level' => 'Proficient', 'reading_rung' => 6]);
        $wholeClass = $this->activity($teacher, ['title' => 'Whole class']);
        $forSupport = $this->activity($teacher, ['title' => 'For the support group']);
        $direct = $this->activity($teacher, ['title' => 'Direct']);
        $viaTag = $this->activity($teacher, ['title' => 'Through the focus group']);

        ActivityAssignment::create(['activity_id' => $wholeClass->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $teacher->id]);
        ActivityAssignment::create(['activity_id' => $forSupport->id, 'class_id' => $class->id, 'reading_band' => 'support', 'assigned_by_teacher_id' => $teacher->id]);
        ActivityAssignment::create(['activity_id' => $direct->id, 'learner_id' => $support->id, 'assigned_by_teacher_id' => $teacher->id]);
        ActivityAssignment::create(['activity_id' => $viaTag->id, 'group_tag' => 'phonics', 'assigned_by_teacher_id' => $teacher->id]);

        $service = app(LearnerAuthService::class);
        $titles = fn (Learner $l) => $service->findActivityOptions($l->fresh())->pluck('activity.title')->sort()->values()->all();

        // The old rule stopped at the first source that had anything and would have shown only "Direct".
        $this->assertSame(['Direct', 'For the support group', 'Through the focus group', 'Whole class'], $titles($support));
        $this->assertSame(['Through the focus group', 'Whole class'], $titles($independent));

        // What a child is shown and what a child may open can never disagree.
        $this->assertTrue($forSupport->isAssignedToLearner($support->fresh()));
        $this->assertFalse($forSupport->isAssignedToLearner($independent->fresh()));
    }

    public function test_a_child_who_moves_up_a_group_gets_that_groups_activities(): void
    {
        $teacher = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->learner(['class_id' => $class->id, 'mastery_level' => 'Beginning', 'reading_rung' => 1]);
        $hard = $this->activity($teacher, ['title' => 'Independent only', 'difficulty_tier' => 'Hard']);
        ActivityAssignment::create(['activity_id' => $hard->id, 'class_id' => $class->id, 'reading_band' => 'independent', 'assigned_by_teacher_id' => $teacher->id]);

        $this->assertFalse($hard->isAssignedToLearner($kid->fresh()));
        $kid->update(['mastery_level' => 'Proficient', 'reading_rung' => 6]);
        $this->assertTrue($hard->isAssignedToLearner($kid->fresh()));
    }

    // ------------------------------------------------------------- the practice stage

    private function loggedInChild(): array
    {
        $teacher = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->learner(['class_id' => $class->id, 'mastery_level' => 'Developing', 'reading_rung' => 3, 'points' => 10, 'streak' => 2]);
        $activity = $this->activity($teacher);
        ActivityAssignment::create(['activity_id' => $activity->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $teacher->id]);
        $this->finishedCheck($kid, $activity);
        $this->actingAs($kid, 'learner');
        config(['services.reading_ai.url' => 'https://reading.test']);

        return [$kid, $activity];
    }

    private function audio(): UploadedFile
    {
        return UploadedFile::fake()->create('recording.webm', 40, 'audio/webm');
    }

    public function test_a_new_activity_starts_with_listen_and_a_finished_one_goes_straight_to_reading(): void
    {
        [$kid, $activity] = $this->loggedInChild();

        $this->get(route('learner.activity.show', $activity))->assertOk()
            ->assertSee('Listen to Tara read it first.')->assertSee('1 Listen')->assertSee('"startAt":"listen"', false)->assertSee('"triesLeft":2', false);

        // Already read for real once: no practice first.
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 80]);
        $this->get(route('learner.activity.show', $activity))->assertOk()->assertSee('"startAt":"real"', false);
        // Any stage can be asked for.
        $this->get(route('learner.activity.show', [$activity, 'stage' => 'listen']))->assertSee('"startAt":"listen"', false);
    }

    public function test_a_practice_try_counts_for_nothing_and_there_are_two_of_them(): void
    {
        [$kid, $activity] = $this->loggedInChild();
        Http::fake(['reading.test/analyze' => Http::response($this->analysis(['accuracy' => 75.0, 'wrong' => [3 => ['hand', 0.3], 5 => ['bad', 0.95]]]))]);

        $first = $this->post(route('learner.activity.practice', $activity), ['audio' => $this->audio()]);
        $first->assertOk()->assertSee('Nice practice')->assertSee('Practice. This one did not count.')->assertSee('6 of 8')->assertSee('Not sure')
            ->assertSee('Try again (1 left)')->assertSee('Not sure words are not counted.');

        // Nothing moved and nothing was saved.
        $kid->refresh();
        $this->assertSame([10, 2, 'Developing'], [$kid->points, $kid->streak, $kid->mastery_level]);
        $this->assertSame(0, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());
        $this->assertSame(0, PersonalWordBank::count());

        $this->post(route('learner.activity.practice', $activity), ['audio' => $this->audio()])->assertOk()->assertDontSee('Try again (');

        // Two tries used: a third goes straight to reading for real, and nothing is sent to the service.
        Http::fake();
        $this->post(route('learner.activity.practice', $activity), ['audio' => $this->audio()])->assertRedirect(route('learner.activity.show', [$activity, 'stage' => 'real']));
        Http::assertNothingSent();
        $this->get(route('learner.activity.show', [$activity, 'stage' => 'try']))->assertSee('"startAt":"real"', false);
    }

    public function test_a_practice_try_that_could_not_be_heard_does_not_use_up_a_try(): void
    {
        [$kid, $activity] = $this->loggedInChild();
        Http::fake(['reading.test/analyze' => Http::response(['detail' => 'Audio is silent or nearly silent.'], 422)]);

        $this->post(route('learner.activity.practice', $activity), ['audio' => $this->audio()])->assertOk()->assertSee("Didn't quite catch that")->assertSee('That try did not count');
        $this->assertSame(2, app(\App\Services\LearnerReadingService::class)->practiceTriesLeft($kid, $activity));
    }

    public function test_practice_is_only_for_activities_the_child_may_open(): void
    {
        [$kid, $activity] = $this->loggedInChild();
        $other = $this->activity($this->teacher(), ['title' => 'Somebody else']);

        $this->post(route('learner.activity.practice', $other), ['audio' => $this->audio()])->assertForbidden();
    }

    public function test_a_real_reading_leaves_not_sure_words_out_of_the_score_the_word_bank_and_the_flag(): void
    {
        [$kid, $activity] = $this->loggedInChild();
        // hen and bat were faint: both not sure. Raw 75%; without them 6 of 6 = 100%.
        Http::fake(['reading.test/analyze' => Http::response($this->analysis(['accuracy' => 75.0, 'wrong' => [3 => ['hand', 0.3], 5 => ['bad', 0.2]]]))]);

        $this->post(route('learner.activity.record', $activity), ['audio' => $this->audio()])->assertOk()
            ->assertSee('6 of 8')->assertSee('Words read right')->assertSee('Not sure')->assertSee('+50');

        $session = ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->firstOrFail();
        $this->assertSame(100.0, (float) $session->accuracy_percent);
        $this->assertFalse((bool) $session->flagged_needs_attention);
        $this->assertSame(0, PersonalWordBank::count(), 'a word the app did not hear well is never put in the child\'s word bank');
        $this->assertTrue($session->word_feedback[3]['unsure']);
        // One perfect reading no longer moves a child a whole level: that takes several strong readings (ReadingProgression).
        $this->assertSame('Developing', $kid->fresh()->mastery_level);
    }

    // ------------------------------------------------------------- Home

    public function test_home_speaks_in_reading_path_steps_and_says_log_out(): void
    {
        [$kid, $activity] = $this->loggedInChild();
        $kid->update(['reading_rung' => 0, 'mastery_level' => 'Beginning', 'subdomain_states' => ['Phonics and Word Study' => ['proficiency' => 0, 'difficulty' => 'easy', 'confidence' => .6, 'attempt_count' => 0]], 'next_recommended_subdomain' => 'Phonics and Word Study', 'next_recommended_difficulty' => 'easy']);

        $page = $this->get(route('learner.dashboard'))->assertOk();
        $page->assertSee('Letter Explorer')->assertSee('Your Reading Path')->assertSee('Letters, then words, then sentences, then stories.')
            ->assertSee('Log out')->assertDontSee('>Switch<', false)->assertDontSee('Your Reading Level')
            ->assertSee('Why this one?')->assertSee('RL1PWS-I-1');
        // Same animations as before: the flame, the star and the flying owl are still the live ones.
        $page->assertSee('owl-bird.json', false)->assertSee('flame-icon.json', false);
    }

    // ------------------------------------------------------------- growing one step at a time

    public function test_a_perfect_reading_shows_how_many_strong_readings_are_still_needed_and_moves_nobody(): void
    {
        [$kid, $activity] = $this->loggedInChild();
        $kid->update(['reading_rung' => 1, 'mastery_level' => 'Beginning', 'rung_changed_at' => now()->subDay()]);
        Http::fake(['reading.test/analyze' => Http::response($this->analysis(['accuracy' => 100.0]))]);

        $this->post(route('learner.activity.record', $activity), ['audio' => $this->audio()])->assertOk()
            ->assertSee('Strong readings toward growing: 1 of 3')->assertDontSee('Level up!');

        $kid->refresh();
        $this->assertSame(1, $kid->reading_rung, 'six or eight words read perfectly once is not a step up');
        $this->assertSame('Beginning', $kid->mastery_level);
    }

    public function test_the_third_strong_reading_of_different_activities_moves_the_child_up_exactly_one_step(): void
    {
        [$kid, $activity] = $this->loggedInChild();
        $kid->update(['reading_rung' => 1, 'mastery_level' => 'Beginning', 'rung_changed_at' => now()->subDay()]);
        $teacher = Teacher::first();
        foreach ([30, 20] as $minutesAgo) {
            $other = $this->activity($teacher, ['title' => "Other {$minutesAgo}"]);
            $row = ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $other->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 95, 'level_before' => 'Beginning', 'level_after' => 'Beginning']);
            $row->timestamp = now()->subMinutes($minutesAgo);
            $row->save();
        }
        Http::fake(['reading.test/analyze' => Http::response($this->analysis(['accuracy' => 100.0]))]);

        $this->post(route('learner.activity.record', $activity), ['audio' => $this->audio()])->assertOk()
            ->assertSee('Level up! You are now a Sentence Reader');

        $kid->refresh();
        $this->assertSame(2, $kid->reading_rung, 'one step, not a jump to the lowest step of the next level');
        $this->assertSame('Beginning', $kid->mastery_level, 'short sentences are still inside Beginning');
        $this->assertNotNull($kid->rung_changed_at);
        $saved = ReadingSession::where('learner_id', $kid->id)->orderByDesc('id')->first();
        $this->assertSame([1, 2], [$saved->rung_before, $saved->rung_after], 'each reading remembers the step before and after, so a move up can be shown to the teacher');
        $this->assertTrue($kid->rung_changed_at->isAfter(now()->subMinute()), 'the count starts again from this move');
        $this->assertTrue(
            \App\Models\Notification::where('message', 'like', '%moved up to Short sentences (Sentence Reader)%')->exists(),
            'the teacher is told the child moved, and to which step'
        );
    }
}

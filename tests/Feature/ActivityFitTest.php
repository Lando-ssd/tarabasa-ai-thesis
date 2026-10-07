<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ActivitySuggestions;
use App\Services\LearnerAuthService;
use App\Services\TeacherAlerts;
use App\Support\ActivityFit;
use App\Support\LearnerProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * A teacher gave a 69 word timed reading to a Grade 1 class whose learner could not yet read words, and the AI suggested
 * long word readings to Grade 1 children. The activity's own level (Easy, Medium, Hard) only says how hard it is for its
 * grade; these pin down that where the CHILD is (the first reading check, or what the Parent said when there is no check
 * yet) now decides whether an activity can be given, suggested or offered first, and that the teacher is shown the
 * parent's profile when a learner joins.
 */
class ActivityFitTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function teacher(): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Tess', 'last_name' => "Teacher{$n}", 'email' => "tt{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "E{$n}", 'status' => 'Active', 'free_generation_credits_remaining' => 2]);

        return [$user, $teacher];
    }

    private function klass(Teacher $t, array $o = []): SchoolClass
    {
        return SchoolClass::create($o + ['teacher_id' => $t->id, 'name' => 'Sampaguita', 'grade_level' => 'Grade 1', 'section' => 'Section A', 'group_tag' => null, 'school_year' => SchoolClass::currentSchoolYear()]);
    }

    /** A child who has not done the first check yet, described by the Parent. */
    private function kid(?SchoolClass $c, array $o = []): Learner
    {
        $n = ++$this->seq;

        return Learner::create($o + [
            'learner_code' => 'TB26-'.(10000 + $n).'0', 'class_id' => $c?->id, 'first_name' => "Kid{$n}", 'last_name' => 'Cruz',
            'grade_level' => $c?->grade_level ?? 'Grade 1', 'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Beginning',
            'reading_stage' => 'starting', 'placement_answers' => ['q1' => 'no', 'q2' => 'no', 'q3' => 'no'],
        ]);
    }

    /** A child whose first reading check landed on this rung. */
    private function checked(?SchoolClass $c, int $rung, string $level, array $o = []): Learner
    {
        $kid = $this->kid($c, ['reading_rung' => $rung, 'mastery_level' => $level] + $o);
        // The first check's own reading: a curated item that belongs to no teacher (so it is never a suggestion).
        $item = $this->activity(Teacher::first() ?? $this->teacher()[1], ['title' => 'Check '.$kid->id, 'word_count' => 6, 'status' => 'Approved', 'purpose' => 'diagnostic']);
        $item->forceFill(['created_by_teacher_id' => null])->save();
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $item->id, 'session_type' => 'Diagnostic', 'initiated_by' => 'Teacher', 'accuracy_percent' => 80, 'wcpm' => 30, 'level_before' => $level, 'level_after' => $level, 'timestamp' => now()]);

        return $kid->fresh();
    }

    private function activity(Teacher $t, array $o = []): Activity
    {
        $tier = $o['difficulty_tier'] ?? 'Easy';

        return Activity::create($o + [
            'created_by_teacher_id' => $t->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => $tier, 'ai_difficulty_tier' => $tier, 'title' => 'Farm words',
            'instructions' => 'Read the words.', 'passage_text' => 'cat dog pig hen cow bat rat', 'word_count' => 7, 'status' => 'Approved', 'reading_features' => ['Familiar words'],
        ]);
    }

    /** The activity in the teacher's example: a Grade 1 Hard timed reading of 69 words. */
    private function busyFarm(Teacher $t, array $o = []): Activity
    {
        return $this->activity($t, $o + ['title' => 'The Busy Farm Animals', 'activity_type' => 'timed_reading', 'difficulty_tier' => 'Hard', 'word_count' => 69, 'passage_text' => 'Several farm animals gather near the wooden fence.']);
    }

    // ---------------------------------------------------------------- where the child is

    public function test_a_child_without_a_check_is_placed_from_what_the_parent_said_and_the_check_wins_once_it_exists(): void
    {
        [, $teacher] = $this->teacher();
        $class = $this->klass($teacher);

        $fresh = $this->kid($class); // Grade 1, "cannot yet recognize letters", all no
        $this->assertSame(['rung' => 0, 'source' => 'parent'], \Illuminate\Support\Arr::only(\App\Support\ReadingLevel::readiness($fresh), ['rung', 'source']));

        $reads = $this->kid($class, ['reading_stage' => 'sentences', 'placement_answers' => ['q1' => 'yes', 'q2' => 'yes', 'q3' => 'yes']]);
        $this->assertSame(3, \App\Support\ReadingLevel::readiness($reads)['rung']);

        $checked = $this->checked($class, 5, 'Proficient', ['reading_stage' => 'starting']);
        $this->assertSame(['rung' => 5, 'source' => 'check'], \Illuminate\Support\Arr::only(\App\Support\ReadingLevel::readiness($checked), ['rung', 'source']));

        $nothing = Learner::create(['learner_code' => 'TB26-999990', 'first_name' => 'No', 'last_name' => 'Data', 'grade_level' => 'Grade 1', 'pin' => '1234', 'avatar_id' => 'A']);
        $this->assertNull(\App\Support\ReadingLevel::readiness($nothing));
        $this->assertSame(ActivityFit::UNKNOWN, ActivityFit::forLearner($this->activity($teacher), $nothing)['verdict']);
    }

    // ---------------------------------------------------------------- the rule itself

    public function test_the_69_word_hard_timed_reading_is_too_long_for_a_child_who_cannot_read_words_yet_but_not_for_a_strong_reader(): void
    {
        [, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $farm = $this->busyFarm($teacher);

        $nonReader = $this->checked($class, 0, 'Beginning');
        $blocked = ActivityFit::forLearner($farm, $nonReader);
        $this->assertSame(ActivityFit::BLOCKED, $blocked['verdict']);
        $this->assertStringContainsString('Letter Explorer', $blocked['note']);
        $this->assertStringContainsString('69 words', $blocked['note']);
        $this->assertStringContainsString('cannot be assigned', $blocked['note']);

        $this->assertSame(ActivityFit::BLOCKED, ActivityFit::forLearner($farm, $this->checked($class, 3, 'Developing'))['verdict']);
        $this->assertSame(ActivityFit::BLOCKED, ActivityFit::forLearner($farm, $this->checked($class, 4, 'Developing'))['verdict'], 'a 45 to 60 word reader is still not ready for 69');
        $this->assertSame(ActivityFit::OK, ActivityFit::forLearner($farm, $this->checked($class, 5, 'Proficient'))['verdict']);
    }

    public function test_a_stretch_can_be_given_with_a_note_and_connected_text_counts_more_for_the_earliest_readers(): void
    {
        [, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $letters = $this->checked($class, 0, 'Beginning');
        $words = $this->checked($class, 1, 'Beginning');

        // 7 words is a little over the 6 a child at the letters step reads comfortably
        $list7 = $this->activity($teacher, ['word_count' => 7]);
        $this->assertSame(ActivityFit::CAUTION, ActivityFit::forLearner($list7, $letters)['verdict']);

        // 8 single words are fine for a word reader, 8 words of a sentence are a stretch
        $this->assertSame(ActivityFit::OK, ActivityFit::forLearner($this->activity($teacher, ['word_count' => 8]), $words)['verdict']);
        $this->assertSame(ActivityFit::CAUTION, ActivityFit::forLearner($this->activity($teacher, ['word_count' => 8, 'activity_type' => 'sentence_reading']), $words)['verdict']);
    }

    public function test_a_class_is_refused_when_a_fifth_of_its_known_learners_cannot_read_it_and_only_warned_below_that(): void
    {
        [, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $farm = $this->busyFarm($teacher);

        // 1 of 5 cannot read it (20 percent): refused
        $five = collect([$this->checked($class, 0, 'Beginning')])->merge(collect(range(1, 4))->map(fn () => $this->checked($class, 6, 'Proficient')));
        $fit = ActivityFit::forAudience($farm, $five, 'this class');
        $this->assertSame(ActivityFit::BLOCKED, $fit['verdict']);
        $this->assertSame(1, $fit['blocked']);
        $this->assertStringContainsString('cannot be assigned to this class', $fit['note']);

        // 1 of 10 (10 percent): can still be given, with a warning that names how many
        $ten = collect([$this->checked($class, 0, 'Beginning')])->merge(collect(range(1, 9))->map(fn () => $this->checked($class, 6, 'Proficient')));
        $warn = ActivityFit::forAudience($farm, $ten, 'this class');
        $this->assertSame(ActivityFit::CAUTION, $warn['verdict']);
        $this->assertStringContainsString('1 of 10', $warn['note']);

        // nobody known: nothing to say
        $this->assertSame(ActivityFit::UNKNOWN, ActivityFit::forAudience($farm, collect(), 'this class')['verdict']);
    }

    // ---------------------------------------------------------------- the teacher cannot force it, on every screen

    public function test_the_class_window_refuses_the_long_activity_for_a_class_that_cannot_read_it_and_allows_a_short_one(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $this->kid($class); // Maria: cannot yet recognize letters
        $farm = $this->busyFarm($teacher);
        $short = $this->activity($teacher, ['title' => 'Six little words', 'word_count' => 6]);
        $this->actingAs($user);

        $this->from('/x')->post(route('teacher.classes.assign-activity', $class), ['activity_id' => $farm->id, 'form' => 'class', 'target_class_id' => $class->id, 'view' => 'assign'])
            ->assertSessionHasErrors('activity_id');
        $this->assertStringContainsString('cannot be assigned', session('errors')->first('activity_id'));
        $this->assertSame(0, ActivityAssignment::count(), 'nothing was assigned');

        $this->post(route('teacher.classes.assign-activity', $class), ['activity_id' => $short->id])->assertSessionHasNoErrors();
        $this->assertSame(1, ActivityAssignment::where('activity_id', $short->id)->count());
    }

    public function test_a_long_activity_can_still_go_to_the_reading_group_that_can_read_it_in_a_mixed_class(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $this->checked($class, 0, 'Beginning');
        $strong = $this->checked($class, 6, 'Proficient');
        $farm = $this->busyFarm($teacher);
        $this->actingAs($user);

        $this->from('/x')->post(route('teacher.classes.assign-activity', $class), ['activity_id' => $farm->id])->assertSessionHasErrors('activity_id');
        $this->assertSame(0, ActivityAssignment::count());

        $this->post(route('teacher.classes.assign-activity', $class), ['activity_id' => $farm->id, 'reading_band' => 'independent'])->assertSessionHasNoErrors();
        $this->assertSame('independent', ActivityAssignment::first()->reading_band);
        $this->assertSame($strong->class_id, $class->id);
    }

    public function test_a_refusal_from_a_suggestion_card_on_the_activities_page_is_shown_there(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $this->kid($class);
        $farm = $this->busyFarm($teacher);
        $this->actingAs($user);

        $this->post(route('teacher.classes.assign-activity', $class), ['activity_id' => $farm->id, 'return' => 'activities'])
            ->assertRedirect(route('teacher.activities.index', ['tab' => 'mine']))->assertSessionHas('classError');
        $this->assertSame(0, ActivityAssignment::count());
    }

    public function test_the_activity_window_refuses_a_learner_a_class_and_a_group_that_cannot_read_it_and_says_so_up_front(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher, ['group_tag' => 'phonics']);
        $kid = $this->kid($class);
        $farm = $this->busyFarm($teacher);
        $this->actingAs($user);

        foreach ([['assign_learner_id' => $kid->id], ['assign_class_id' => $class->id], ['assign_group_tag' => 'phonics']] as $target) {
            $this->from('/x')->post(route('teacher.activities.assign', $farm), $target)->assertSessionHasErrors('assign_target');
            $this->assertStringContainsString('cannot be assigned', session('errors')->first('assign_target'));
        }
        $this->assertSame(0, ActivityAssignment::count());

        // the window tells the teacher before the button is pressed
        $this->get(route('teacher.activities.window', $farm))->assertOk()->assertSee('data-fit="blocked"', false)->assertSee('Too long');
    }

    public function test_an_alert_cannot_give_an_activity_that_is_too_long_either(): void
    {
        [, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->checked($class, 0, 'Beginning');
        $farm = $this->busyFarm($teacher);

        $this->expectException(ValidationException::class);
        try {
            app(TeacherAlerts::class)->assign($teacher, $kid, $farm, 'support');
        } finally {
            $this->assertSame(0, ActivityAssignment::count());
        }
    }

    public function test_the_class_page_carries_the_fit_for_the_assign_window(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $this->kid($class);
        $this->busyFarm($teacher);
        $this->actingAs($user);

        $page = $this->get(route('teacher.classes.index'))->assertOk();
        // the page's data says, for this class and this activity, that it cannot be assigned, and the window has a place for the note
        $page->assertSee('cannot be assigned to this class', false)->assertSee('"v":"blocked"', false)->assertSee('data-assign-fit', false);
    }

    // ---------------------------------------------------------------- suggestions follow where the child is

    public function test_suggestions_never_offer_an_activity_that_is_too_long_for_the_group_or_the_learner(): void
    {
        [, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->checked($class, 0, 'Beginning');
        $longEasy = $this->activity($teacher, ['title' => 'Too long to be Easy', 'word_count' => 69, 'activity_type' => 'timed_reading']);
        $fits = $this->activity($teacher, ['title' => 'Six little words', 'word_count' => 6]);

        $suggestions = app(ActivitySuggestions::class);
        $group = $suggestions->forGroup($teacher, $class, 'support', collect([$kid]));
        $this->assertSame(['Six little words'], $group->map(fn ($c) => $c['activity']->title)->all());

        $one = $suggestions->forLearner($teacher, $kid, 'Easy');
        $this->assertSame($fits->id, $one['activity']->id);

        // with nothing short enough left, nothing is suggested (a fresh instance: the approved list is cached per request)
        $fits->update(['status' => 'Draft']);
        $this->assertNull(app(ActivitySuggestions::class)->forLearner($teacher, $kid, 'Easy'));
        $this->assertSame('Approved', $longEasy->fresh()->status, 'the long one is still approved, just never suggested');
    }

    public function test_among_activities_that_fit_the_one_about_what_the_child_likes_comes_first(): void
    {
        [, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->checked($class, 1, 'Beginning', ['interests' => ['vehicles']]);
        $this->activity($teacher, ['title' => 'Garden words', 'word_count' => 8, 'topic' => 'Nature']);
        $cars = $this->activity($teacher, ['title' => 'Jeep and bus words', 'word_count' => 8, 'topic' => 'Vehicles']);

        $pick = app(ActivitySuggestions::class)->forLearner($teacher, $kid, 'Easy');

        $this->assertSame($cars->id, $pick['activity']->id);
    }

    public function test_a_child_is_offered_the_ones_they_can_read_first_and_the_long_one_waits_marked_for_later(): void
    {
        [, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->checked($class, 0, 'Beginning');
        $farm = $this->busyFarm($teacher);
        $short = $this->activity($teacher, ['title' => 'Six little words', 'word_count' => 6]);
        // the long one was given first, as it would have been before this rule existed
        ActivityAssignment::create(['activity_id' => $farm->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $teacher->id]);
        ActivityAssignment::create(['activity_id' => $short->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $teacher->id]);

        $options = app(LearnerAuthService::class)->findActivityOptions($kid);

        $this->assertSame([$short->id, $farm->id], $options->map(fn ($o) => $o['activity']->id)->all());
        $this->assertArrayNotHasKey('later', $options[0]);
        $this->assertTrue($options[1]['later']);
        $this->assertStringNotContainsString('Picked just for you', $options[1]['source']);
    }

    // ---------------------------------------------------------------- the parent's profile, for the teacher

    public function test_a_learner_who_joins_opens_on_their_own_page_with_what_the_parent_shared_and_what_suits_them(): void
    {
        [$user, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->kid(null, [
            'first_name' => 'Maria', 'reading_stage' => 'blending', 'home_language' => 'Cebuano',
            'supports' => ['games', 'read_aloud'], 'interests' => ['animals'],
            'placement_answers' => ['q1' => 'yes', 'q2' => 'no', 'q3' => 'unsure'],
        ]);
        $this->actingAs($user);

        $this->post(route('teacher.classes.join-learner', $class), ['learner_code' => substr($kid->learner_code, -5)])
            ->assertRedirect(route('teacher.classes.index', ['school_year' => $class->school_year, 'open' => $class->id, 'tab' => 'learner-'.$kid->id]));

        $page = $this->get(route('teacher.classes.index', ['school_year' => $class->school_year, 'open' => $class->id, 'tab' => 'learner-'.$kid->id]))->assertOk();
        $page->assertSee('What the parent shared')->assertSee('Reads simple words')->assertSee('Cebuano')->assertSee('Short games')
            ->assertSee('Hearing the words read aloud')->assertSee('Animals')->assertSee('Where Maria reads now')->assertSee('What suits Maria now')
            ->assertSee('data-autoview="learner-'.$kid->id.'"', false);
        $page->assertSee('Can your child identify and name most letters of the alphabet?')->assertSee('Not sure');
    }

    public function test_the_profile_says_when_the_check_and_the_parent_disagree_and_that_the_check_is_the_more_recent_measure(): void
    {
        [, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        // The parent said the child reads simple words (the check would start at rung 1); the check found letters (rung 0): gap 1, no note.
        $close = $this->checked($class, 0, 'Beginning', ['reading_stage' => 'blending']);
        $this->assertNull(LearnerProfile::forTeacher($close)['mismatch']);

        // The parent said the child reads grade level passages; the check found letters: two or more rungs apart.
        $far = $this->checked($class, 0, 'Beginning', ['first_name' => 'Ben', 'reading_stage' => 'independent', 'placement_answers' => null]);
        $profile = LearnerProfile::forTeacher($far);
        $this->assertStringContainsString('lower than the parent described', $profile['mismatch']);
        $this->assertStringContainsString('more recent measure', $profile['mismatch']);
        $this->assertSame('Letter Explorer', $profile['check']['step']);
        $this->assertStringContainsString('letters', strtolower($profile['bestFit']['advice']));
    }

    public function test_a_child_whose_first_check_is_not_done_is_told_where_it_will_start(): void
    {
        [, $teacher] = $this->teacher();
        $class = $this->klass($teacher);
        $kid = $this->kid($class, ['reading_stage' => 'sentences', 'placement_answers' => ['q1' => 'yes', 'q2' => 'yes', 'q3' => 'yes']]);

        $profile = LearnerProfile::forTeacher($kid);

        $this->assertFalse($profile['check']['done']);
        $this->assertSame('Sentence Reader', $profile['startsAt']);
        $this->assertSame('Reads short sentences', $profile['stage']);
        $this->assertCount(3, $profile['answers']);
    }
}

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
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The phone app's Learner API (routes/api.php): sign in with a token, the Home numbers, what to read, the practice
 * tries, a real reading with its quiz, and the wake call. It must say the same things the website says, never send a
 * quiz answer to the phone, and keep practice and real readings apart.
 */
class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function teacher(): Teacher
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "m{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);

        return Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "M{$n}", 'status' => 'Active']);
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
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'ai_difficulty_tier' => 'Easy', 'title' => 'Farm Words', 'instructions' => 'Read the words.',
            'passage_text' => 'cat dog pig hen cow bat rat fox', 'reference_text' => 'cat dog pig hen cow bat rat fox', 'word_count' => 8, 'status' => 'Approved',
        ]);
    }

    private function finishedCheck(Learner $kid, Activity $any): void
    {
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $any->id, 'session_type' => 'Diagnostic', 'initiated_by' => 'Teacher', 'accuracy_percent' => 80]);
    }

    /** @return array{0:Learner,1:Activity,2:Teacher} a child who finished the first check, with one activity given to the class */
    private function child(array $o = []): array
    {
        $teacher = $this->teacher();
        $class = SchoolClass::create(['teacher_id' => $teacher->id, 'name' => 'Rizal', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $kid = $this->learner($o + ['class_id' => $class->id]);
        $activity = $this->activity($teacher);
        ActivityAssignment::create(['activity_id' => $activity->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $teacher->id]);
        $this->finishedCheck($kid, $activity);
        config(['services.reading_ai.url' => 'https://reading.test']);

        return [$kid, $activity, $teacher];
    }

    private function audio(): UploadedFile
    {
        return UploadedFile::fake()->create('recording.m4a', 40, 'audio/mp4');
    }

    /** A Reading-api /analyze answer for "cat dog pig hen cow bat rat fox". */
    private function analysis(float $accuracy = 100.0, array $wrong = []): array
    {
        $words = ['cat', 'dog', 'pig', 'hen', 'cow', 'bat', 'rat', 'fox'];
        $feedback = [];
        $stamps = [];
        foreach ($words as $i => $w) {
            $bad = $wrong[$i] ?? null;
            $feedback[] = $bad ? ['reference' => $w, 'spoken' => $bad[0], 'status' => 'substitution'] : ['reference' => $w, 'spoken' => $w, 'status' => 'correct'];
            $stamps[] = ['word' => $bad[0] ?? $w, 'start' => $i, 'end' => $i + .5, 'confidence' => $bad[1] ?? 1.0];
        }

        return [
            'accuracy' => ['accuracy_score' => $accuracy, 'spoken_word_count' => 8, 'word_feedback' => $feedback, 'substitutions' => count($wrong), 'deletions' => 0, 'insertions' => 0],
            'speed' => ['wcpm' => 60, 'speed_score' => 70],
            'prosody' => ['prosody_score' => 60],
            'word_timestamps' => $stamps,
        ];
    }

    // ------------------------------------------------------------- signing in

    public function test_a_child_signs_in_with_the_code_and_pin_and_gets_a_token_and_the_new_fields(): void
    {
        [$kid] = $this->child(['mastery_level' => 'Beginning', 'reading_rung' => 1]);

        $login = $this->postJson('/api/learner/login', ['learner_code' => strtolower($kid->learner_code), 'pin' => '1234'])->assertOk();

        $this->assertNotEmpty($login->json('token'));
        $this->assertFalse($login->json('needsDiagnostic'));
        $this->assertSame($kid->learner_code, $login->json('learner.learnerCode'));
        $this->assertSame(['step' => 2, 'name' => 'Word Builder'], $login->json('learner.readingStep'));
        $this->assertSame('blue', $login->json('learner.themeColor'));
        $this->assertSame(0, $login->json('learner.dayStreak'));
        // Never anything secret about the child in the payload.
        $this->assertArrayNotHasKey('pin', $login->json('learner'));

        // The token works on a protected call, and the call without it is a plain 401.
        $this->withToken($login->json('token'))->getJson('/api/learner/dashboard')->assertOk();
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/learner/dashboard')->assertUnauthorized();
    }

    public function test_a_wrong_pin_is_refused_with_one_plain_message_and_a_child_with_no_first_check_is_told_so(): void
    {
        $kid = $this->learner(['mastery_level' => null, 'reading_rung' => null]);

        // A wrong PIN and an unknown code get the very same message, so nobody can tell which one was wrong.
        $this->postJson('/api/learner/login', ['learner_code' => $kid->learner_code, 'pin' => '0000'])->assertUnprocessable()->assertJsonPath('errors.pin.0', 'Incorrect code or PIN.');
        $this->postJson('/api/learner/login', ['learner_code' => 'TB26-00000', 'pin' => '1234'])->assertUnprocessable()->assertJsonPath('errors.pin.0', 'Incorrect code or PIN.');

        $login = $this->postJson('/api/learner/login', ['learner_code' => $kid->learner_code, 'pin' => '1234'])->assertOk();
        $this->assertTrue($login->json('needsDiagnostic'));
        $this->assertNull($login->json('learner.readingStep.step'));
        $this->assertSame('New Reader', $login->json('learner.readingStep.name'));
    }

    public function test_logging_out_really_ends_the_token(): void
    {
        [$kid] = $this->child();
        $token = $this->postJson('/api/learner/login', ['learner_code' => $kid->learner_code, 'pin' => '1234'])->json('token');

        $this->withToken($token)->postJson('/api/learner/logout')->assertOk();

        $this->assertSame(0, $kid->tokens()->count());
    }

    // ------------------------------------------------------------- Home

    public function test_home_carries_the_reading_path_the_day_streak_the_weekly_goal_and_all_the_badges(): void
    {
        [$kid, $activity] = $this->child(['mastery_level' => 'Developing', 'reading_rung' => 3]);
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 90]);
        Sanctum::actingAs($kid, ['*']);

        $home = $this->getJson('/api/learner/dashboard')->assertOk();

        $path = $home->json('readingPath');
        $this->assertSame(['Letter Explorer', 'Word Builder', 'Sentence Reader', 'Story Reader'], array_column($path, 'name'));
        $this->assertSame([false, false, true, false], array_column($path, 'isCurrent'), 'the child is marked on their own step');
        $this->assertSame(1, $home->json('streak.days'));
        $this->assertCount(7, $home->json('streak.week'));
        $this->assertSame(['done' => 1, 'target' => 5, 'met' => false], $home->json('weeklyGoal'));
        $this->assertCount(7, $home->json('growth.days'));
        $this->assertGreaterThanOrEqual(100, count($home->json('badges')), 'all the badges are listed, earned or not');
    }

    public function test_home_is_not_available_before_the_first_check_and_says_why(): void
    {
        $kid = $this->learner(['mastery_level' => null, 'reading_rung' => null]);
        Sanctum::actingAs($kid, ['*']);

        $this->getJson('/api/learner/dashboard')->assertForbidden()->assertJsonPath('error', 'diagnostic_required');
    }

    public function test_the_website_home_and_the_phone_home_share_one_set_of_numbers(): void
    {
        [$kid, $activity] = $this->child();
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 90]);

        $shared = \App\Support\LearnerHome::for($kid);
        Sanctum::actingAs($kid, ['*']);
        $home = $this->getJson('/api/learner/dashboard')->json();

        $this->assertSame($shared['dayStreak'], $home['streak']['days']);
        $this->assertSame($shared['weeklyCount'], $home['weeklyGoal']['done']);
        $this->actingAs($kid, 'learner')->get(route('learner.dashboard'))->assertOk();
    }

    // ------------------------------------------------------------- what to read

    public function test_the_activity_list_and_detail_never_send_a_quiz_answer_to_the_phone(): void
    {
        [$kid, , $teacher] = $this->child();
        $quiz = $this->activity($teacher, [
            'title' => 'Coral Reef', 'competency' => 'reading_comprehension', 'competency_label' => 'Reading Comprehension', 'activity_type' => 'comprehension',
            'follow_up_questions' => [
                ['question' => 'What lived in the reef?', 'choices' => ['A green turtle', 'A red bus', 'A blue shoe'], 'answer' => 'A green turtle', 'explanation' => 'It says the turtle lived there.'],
                ['question' => 'Where was it?', 'choices' => ['In the sea', 'On the moon'], 'answer' => 'In the sea', 'explanation' => 'The reef is in the sea.'],
            ],
        ]);
        ActivityAssignment::create(['activity_id' => $quiz->id, 'class_id' => $kid->class_id, 'assigned_by_teacher_id' => $teacher->id]);
        Sanctum::actingAs($kid, ['*']);

        $list = $this->getJson('/api/learner/activities')->assertOk();
        $this->assertCount(2, $list->json('options'));

        $detail = $this->getJson("/api/learner/activities/{$quiz->id}")->assertOk();
        $this->assertSame('Read the words.', $detail->json('instructions'));
        $this->assertSame([
            ['question' => 'What lived in the reef?', 'choices' => ['A green turtle', 'A red bus', 'A blue shoe']],
            ['question' => 'Where was it?', 'choices' => ['In the sea', 'On the moon']],
        ], $detail->json('quizQuestions'));
        $body = $detail->getContent();
        $this->assertStringNotContainsString('explanation', $body);
        $this->assertStringNotContainsString('"answer"', $body);
        $this->assertStringNotContainsString('It says the turtle lived there.', $body);
        $this->assertSame(['triesLeft' => 2, 'startAt' => 'listen'], $detail->json('practice'));
    }

    public function test_an_activity_with_no_quiz_has_an_empty_quiz_and_a_child_cannot_open_somebody_elses(): void
    {
        [$kid, $activity] = $this->child();
        $other = $this->activity($this->teacher(), ['title' => 'Somebody else']);
        Sanctum::actingAs($kid, ['*']);

        $this->getJson("/api/learner/activities/{$activity->id}")->assertOk()->assertJsonPath('quizQuestions', []);
        $this->getJson("/api/learner/activities/{$other->id}")->assertForbidden();
    }

    public function test_a_finished_activity_starts_at_reading_for_real(): void
    {
        [$kid, $activity] = $this->child();
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 80]);
        Sanctum::actingAs($kid, ['*']);

        $this->getJson("/api/learner/activities/{$activity->id}")->assertJsonPath('practice.startAt', 'real');
        $this->getJson("/api/learner/activities/{$activity->id}?stage=listen")->assertJsonPath('practice.startAt', 'listen');
    }

    // ------------------------------------------------------------- the two free practice tries

    public function test_a_practice_try_counts_for_nothing_and_there_are_two_of_them(): void
    {
        [$kid, $activity] = $this->child(['points' => 10, 'streak' => 2]);
        Sanctum::actingAs($kid, ['*']);
        Http::fake(['reading.test/analyze' => Http::response($this->analysis(75.0, [3 => ['hand', 0.3], 5 => ['bad', 0.95]]))]);

        $first = $this->post("/api/learner/activities/{$activity->id}/practice", ['audio' => $this->audio()], ['Accept' => 'application/json'])->assertOk();
        $this->assertSame('scored', $first->json('status'));
        $this->assertSame(1, $first->json('triesLeft'));
        $this->assertNotEmpty($first->json('wordBreakdown'));
        $this->assertNotNull($first->json('wordCounts'), 'the "N of M words read right" numbers come with the feedback');

        // Nothing moved and nothing was saved.
        $kid->refresh();
        $this->assertSame([10, 2, 'Developing'], [$kid->points, $kid->streak, $kid->mastery_level]);
        $this->assertSame(0, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());
        $this->assertSame(0, PersonalWordBank::count());

        $this->post("/api/learner/activities/{$activity->id}/practice", ['audio' => $this->audio()], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('triesLeft', 0);

        // Both tries used: nothing is sent to the scoring service any more.
        Http::fake();
        $this->post("/api/learner/activities/{$activity->id}/practice", ['audio' => $this->audio()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('status', 'no_tries_left');
        Http::assertNothingSent();
        $this->getJson("/api/learner/activities/{$activity->id}?stage=try")->assertJsonPath('practice.startAt', 'real');
    }

    public function test_a_practice_try_that_could_not_be_heard_does_not_use_up_a_try(): void
    {
        [$kid, $activity] = $this->child();
        Sanctum::actingAs($kid, ['*']);
        Http::fake(['reading.test/analyze' => Http::response(['detail' => 'Audio is silent or nearly silent.'], 422)]);

        $this->post("/api/learner/activities/{$activity->id}/practice", ['audio' => $this->audio()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('status', 'unclear')->assertJsonPath('triesLeft', 2);
    }

    public function test_practice_needs_a_recording_and_an_activity_the_child_may_open(): void
    {
        [$kid, $activity] = $this->child();
        $other = $this->activity($this->teacher(), ['title' => 'Somebody else']);
        Sanctum::actingAs($kid, ['*']);

        $this->postJson("/api/learner/activities/{$other->id}/practice")->assertForbidden();
        $this->postJson("/api/learner/activities/{$activity->id}/practice")->assertUnprocessable();
    }

    // ------------------------------------------------------------- a real reading

    public function test_a_real_reading_is_scored_saved_and_returns_what_the_results_need(): void
    {
        [$kid, $activity] = $this->child(['points' => 0]);
        Sanctum::actingAs($kid, ['*']);
        Http::fake(['reading.test/analyze' => Http::response($this->analysis(75.0, [3 => ['hand', 0.3], 5 => ['bad', 0.95]]))]);

        $result = $this->post("/api/learner/activities/{$activity->id}/record", ['audio' => $this->audio()], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame('scored', $result->json('status'));
        $this->assertSame($activity->id, $result->json('activityId'));
        $this->assertGreaterThan(0, $result->json('pointsEarned'));
        $this->assertNotEmpty($result->json('wordBreakdown'));
        $this->assertSame(1, $result->json('learner.dayStreak'), 'the reading counts toward the day streak right away');
        $this->assertNotNull($result->json('progress'));
        $this->assertSame(1, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());
    }

    public function test_the_comprehension_quiz_is_marked_on_the_server_from_the_picked_choice_text(): void
    {
        [$kid, , $teacher] = $this->child();
        $quiz = $this->activity($teacher, [
            'title' => 'Coral Reef', 'competency' => 'reading_comprehension', 'competency_label' => 'Reading Comprehension', 'activity_type' => 'comprehension',
            'follow_up_questions' => [
                ['question' => 'What lived in the reef?', 'choices' => ['A green turtle', 'A red bus'], 'answer' => 'A green turtle', 'explanation' => 'x'],
                ['question' => 'Where was it?', 'choices' => ['In the sea', 'On the moon'], 'answer' => 'In the sea', 'explanation' => 'y'],
            ],
        ]);
        ActivityAssignment::create(['activity_id' => $quiz->id, 'class_id' => $kid->class_id, 'assigned_by_teacher_id' => $teacher->id]);
        Sanctum::actingAs($kid, ['*']);
        Http::fake(['reading.test/analyze' => Http::response($this->analysis(100.0))]);

        $result = $this->post("/api/learner/activities/{$quiz->id}/record", ['audio' => $this->audio(), 'answers' => ['A green turtle', 'On the moon']], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(1, $result->json('comprehension.correctCount'));
        $this->assertSame(2, $result->json('comprehension.totalCount'));
        $this->assertEqualsWithDelta(50.0, (float) ReadingSession::where('learner_id', $kid->id)->latest('id')->value('comprehension_score'), 0.01);
    }

    public function test_a_reading_that_could_not_be_heard_saves_nothing(): void
    {
        [$kid, $activity] = $this->child();
        Sanctum::actingAs($kid, ['*']);
        Http::fake(['reading.test/analyze' => Http::response(['detail' => 'Audio is silent or nearly silent.'], 422)]);

        $this->post("/api/learner/activities/{$activity->id}/record", ['audio' => $this->audio()], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('status', 'unclear')->assertJsonPath('final', false);
        $this->assertSame(0, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());
    }

    // ------------------------------------------------------------- the small ones

    public function test_the_text_size_choice_is_saved_and_must_be_one_to_five(): void
    {
        [$kid] = $this->child();
        Sanctum::actingAs($kid, ['*']);

        $this->postJson('/api/learner/reading-preferences/font-step', ['step' => 5])->assertOk();
        $this->assertSame(5, $kid->fresh()->reading_font_step);
        $this->postJson('/api/learner/reading-preferences/font-step', ['step' => 6])->assertUnprocessable();
    }

    public function test_the_wake_call_answers_at_once_and_needs_a_token(): void
    {
        [$kid] = $this->child();
        config(['services.reading_ai.url' => 'https://reading.test']);
        Http::fake();

        $this->getJson('/api/learner/warm')->assertUnauthorized();

        Sanctum::actingAs($kid, ['*']);
        $this->getJson('/api/learner/warm')->assertNoContent();
    }
}

<?php

namespace Tests\Feature;

use App\Jobs\ScoreRecordingJob;
use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Models\SchoolClass;
use App\Models\ScoringJob;
use App\Models\Teacher;
use App\Models\User;
use App\Services\BackgroundScoring;
use App\Services\LearnerDiagnosticService;
use App\Support\LearnerCode;
use App\Support\OutcomeCodec;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Recordings are scored in the background: the web request only saves the recording and a worker scores it, so a class pressing
 * "done" together can no longer hold every web worker (the 502s of 2026-10-22 and 2026-10-23). The scoring itself is unchanged,
 * so the results page is the same screen; these tests pin the new parts: the hand-over, the status and result addresses,
 * "never counted twice", who may see a result, and the temporary recording being deleted.
 *
 * The test queue is "sync", which scores inside the dispatch (a worker's job done at once); Queue::fake() is used where the
 * point is the in-between state ("queued").
 */
class BackgroundScoringTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private bool $analyzeFaked = false;

    private float $nextAccuracy = 60.0;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.reading_ai.url' => 'https://reading.test',
            // Nothing in this file may reach a real service.
            'services.adaptive_recommender.url' => null,
            'services.adaptive_recommender.key' => null,
            'services.activity_ai.url' => null,
        ]);
        File::deleteDirectory(ScoringJob::audioFolder());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(ScoringJob::audioFolder());
        parent::tearDown();
    }

    // ------------------------------------------------------------------ fixtures

    private function teacher(): Teacher
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "t{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);

        return Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "E{$n}", 'status' => 'Active']);
    }

    private function learner(array $o = []): Learner
    {
        $n = ++$this->seq;

        return Learner::create($o + [
            'learner_code' => LearnerCode::generate(), 'first_name' => "Kid{$n}", 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Developing', 'reading_rung' => 3, 'points' => 10, 'streak' => 1,
        ]);
    }

    /** A child who has finished the first check and may read one activity. */
    private function child(): array
    {
        $teacher = $this->teacher();
        $class = SchoolClass::create(['teacher_id' => $teacher->id, 'name' => 'Rizal', 'grade_level' => 'Grade 1', 'section' => 'A', 'school_year' => SchoolClass::currentSchoolYear()]);
        $kid = $this->learner(['class_id' => $class->id]);
        $activity = Activity::create([
            'created_by_teacher_id' => $teacher->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'ai_difficulty_tier' => 'Easy', 'title' => 'Farm Words', 'instructions' => 'Read.',
            'passage_text' => 'cat dog pig hen cow bat rat fox', 'reference_text' => 'cat dog pig hen cow bat rat fox', 'word_count' => 8, 'status' => 'Approved',
        ]);
        ActivityAssignment::create(['activity_id' => $activity->id, 'class_id' => $class->id, 'assigned_by_teacher_id' => $teacher->id]);
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Diagnostic', 'initiated_by' => 'Teacher', 'accuracy_percent' => 80]);

        return [$kid, $activity];
    }

    /** What the speech service answers for "cat dog pig hen cow bat rat fox". */
    private function analysis(float $accuracy): array
    {
        $words = ['cat', 'dog', 'pig', 'hen', 'cow', 'bat', 'rat', 'fox'];

        return [
            'accuracy' => [
                'accuracy_score' => $accuracy, 'spoken_word_count' => 8, 'substitutions' => 0, 'deletions' => 0, 'insertions' => 0,
                'word_feedback' => array_map(fn ($w) => ['reference' => $w, 'spoken' => $w, 'status' => 'correct'], $words),
            ],
            'speed' => ['wcpm' => 60, 'speed_score' => 70],
            'prosody' => ['prosody_score' => 60],
            'word_timestamps' => array_map(fn ($w, $i) => ['word' => $w, 'start' => $i, 'end' => $i + .5, 'confidence' => 0.95], $words, array_keys($words)),
        ];
    }

    /** One fake for the whole test (Http::fake keeps the FIRST answer for a URL), answering whatever score was set last. */
    private function speechSays(float $accuracy): void
    {
        $this->nextAccuracy = $accuracy;

        if (! $this->analyzeFaked) {
            Http::fake(['reading.test/analyze' => fn () => Http::response($this->analysis($this->nextAccuracy))]);
            $this->analyzeFaked = true;
        }
    }

    private function audio(): UploadedFile
    {
        return UploadedFile::fake()->create('recording.webm', 40, 'audio/webm');
    }

    private function send(string $routeName, mixed $params, string $key, array $extra = [])
    {
        return $this->postJson(route($routeName, $params), ['audio' => $this->audio(), 'client_key' => $key] + $extra);
    }

    private function files(): array
    {
        return File::isDirectory(ScoringJob::audioFolder()) ? array_map('basename', File::files(ScoringJob::audioFolder())) : [];
    }

    // ------------------------------------------------------------------ a scored reading

    public function test_a_reading_sent_in_the_background_is_scored_and_the_result_page_is_the_same_screen(): void
    {
        [$kid, $activity] = $this->child();
        $this->speechSays(60.0);
        $this->actingAs($kid, 'learner');

        $answer = $this->send('learner.activity.record', $activity, 'key-1')->assertOk()->assertJsonPath('status', 'done');
        $this->assertNotNull($answer->json('resultUrl'));
        $this->assertNotNull($answer->json('statusUrl'));

        // It was scored once, with the same effects as scoring inside the request.
        $this->assertSame(1, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());
        $this->assertSame(10 + 30, $kid->fresh()->points);

        $this->get($answer->json('resultUrl'))->assertOk()->assertViewIs('learner.reading-results')
            ->assertViewHas('accuracy', 60.0)->assertViewHas('pointsEarned', 30)
            ->assertViewHas('activity', fn ($a) => $a instanceof Activity && $a->id === $activity->id)
            ->assertViewHas('learner', fn ($l) => $l instanceof Learner && $l->id === $kid->id);
    }

    public function test_the_recording_is_deleted_as_soon_as_it_has_been_scored(): void
    {
        [$kid, $activity] = $this->child();
        $this->speechSays(80.0);
        $this->actingAs($kid, 'learner');

        $this->send('learner.activity.record', $activity, 'key-1')->assertOk();

        $this->assertSame([], $this->files(), 'no recording may stay on the server once it is scored');
        $job = ScoringJob::first();
        $this->assertNull($job->audio_path);
        $this->assertSame(ScoringJob::DONE, $job->status);
    }

    public function test_a_plain_form_post_is_still_scored_inside_the_request(): void
    {
        [$kid, $activity] = $this->child();
        $this->speechSays(70.0);
        $this->actingAs($kid, 'learner');

        // A browser that cannot send in the background posts the form itself and gets the page back, as before.
        $this->post(route('learner.activity.record', $activity), ['audio' => $this->audio()])
            ->assertOk()->assertViewIs('learner.reading-results');

        $this->assertSame(0, ScoringJob::count());
        $this->assertSame(1, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());
    }

    public function test_a_queued_recording_waits_for_a_worker_on_its_own_queue_and_is_not_scored_yet(): void
    {
        Queue::fake();
        [$kid, $activity] = $this->child();
        $this->speechSays(60.0);
        $this->actingAs($kid, 'learner');

        $answer = $this->send('learner.activity.record', $activity, 'key-1')->assertStatus(202)->assertJsonPath('status', 'queued');

        Queue::assertPushedOn('scoring', ScoreRecordingJob::class);
        $this->assertCount(1, $this->files(), 'the recording waits in a temporary file');
        $this->assertSame(0, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());
        $this->assertNull($answer->json('resultUrl'));

        // A worker takes it.
        app(BackgroundScoring::class)->run($answer->json('id'));

        $this->getJson($answer->json('statusUrl'))->assertOk()->assertJsonPath('status', 'done')->assertJsonStructure(['resultUrl']);
        $this->assertSame([], $this->files());
        $this->assertSame(1, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());
    }

    public function test_two_workers_cannot_both_score_the_same_recording(): void
    {
        Queue::fake();
        [$kid, $activity] = $this->child();
        $this->speechSays(60.0);
        $this->actingAs($kid, 'learner');

        $id = $this->send('learner.activity.record', $activity, 'key-1')->json('id');
        app(BackgroundScoring::class)->run($id);
        app(BackgroundScoring::class)->run($id);

        $this->assertSame(1, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());
        $this->assertSame(10 + 30, $kid->fresh()->points, 'points are paid once');
    }

    // ------------------------------------------------------------------ never counted twice

    public function test_sending_the_same_recording_again_is_not_counted_again(): void
    {
        [$kid, $activity] = $this->child();
        $this->speechSays(60.0);
        $this->actingAs($kid, 'learner');

        $first = $this->send('learner.activity.record', $activity, 'same-key')->assertOk();
        $second = $this->send('learner.activity.record', $activity, 'same-key')->assertOk();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, ScoringJob::count());
        $this->assertSame(1, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());
        $this->assertSame(10 + 30, $kid->fresh()->points);
    }

    public function test_a_second_recording_while_one_is_still_being_scored_shows_the_first_one(): void
    {
        Queue::fake();
        [$kid, $activity] = $this->child();
        $this->actingAs($kid, 'learner');

        $first = $this->send('learner.activity.record', $activity, 'key-a')->assertStatus(202);
        $second = $this->send('learner.activity.record', $activity, 'key-b')->assertStatus(202);

        $this->assertSame($first->json('id'), $second->json('id'), 'that reading is already counting; a second would count twice');
        $this->assertSame(1, ScoringJob::count());
        $this->assertCount(1, $this->files());
    }

    public function test_a_failed_recording_can_be_sent_again_with_the_same_key(): void
    {
        [$kid, $activity] = $this->child();
        $this->actingAs($kid, 'learner');

        // The speech service is not set up: a plain reason, nothing scored, nothing paid.
        config(['services.reading_ai.url' => null]);
        $failed = $this->send('learner.activity.record', $activity, 'retry-key')->assertOk()->assertJsonPath('status', 'failed');
        $this->assertStringContainsString("isn't configured", $failed->json('message'));
        $this->assertSame(0, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());
        $this->assertSame([], $this->files());

        // "Send Again" once it works.
        config(['services.reading_ai.url' => 'https://reading.test']);
        $this->speechSays(90.0);
        $this->send('learner.activity.record', $activity, 'retry-key')->assertOk()->assertJsonPath('status', 'done');

        $this->assertSame(1, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());
        $this->assertSame(1, ScoringJob::where('client_key', 'retry-key')->count());
    }

    // ------------------------------------------------------------------ what a child hears back

    public function test_a_recording_that_could_not_be_heard_gets_the_try_again_screen_and_no_session(): void
    {
        [$kid, $activity] = $this->child();
        Http::fake(['reading.test/analyze' => Http::response(['detail' => 'Audio is silent or nearly silent.'], 422)]);
        $this->actingAs($kid, 'learner');

        $answer = $this->send('learner.activity.record', $activity, 'key-1')->assertOk()->assertJsonPath('status', 'done');

        $this->get($answer->json('resultUrl'))->assertOk()->assertViewIs('learner.reading-unclear')->assertViewHas('final', false);
        $this->assertSame(0, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());
    }

    public function test_a_missing_recording_is_a_normal_validation_answer_and_creates_nothing(): void
    {
        [$kid, $activity] = $this->child();
        $this->actingAs($kid, 'learner');

        $this->postJson(route('learner.activity.record', $activity), ['client_key' => 'x'])->assertStatus(422)->assertJsonValidationErrors('audio');
        $this->assertSame(0, ScoringJob::count());
    }

    public function test_the_quiz_answers_travel_with_the_recording(): void
    {
        [$kid, $activity] = $this->child();
        $activity->update(['competency' => 'reading_comprehension', 'follow_up_questions' => [
            ['question' => 'What is it?', 'choices' => ['A cat', 'A dog'], 'answer' => 'A cat', 'explanation' => 'Cat.'],
        ]]);
        $this->speechSays(100.0);
        $this->actingAs($kid, 'learner');

        $answer = $this->send('learner.activity.record', $activity, 'quiz-key', ['answers' => ['A cat']])->assertOk();

        $this->get($answer->json('resultUrl'))->assertOk()
            ->assertViewHas('comprehension', fn ($c) => $c !== null && $c['correctCount'] === 1 && $c['totalCount'] === 1);
        $this->assertEquals(100.0, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->value('comprehension_score'));
    }

    // ------------------------------------------------------------------ who may see it

    public function test_another_child_cannot_ask_about_or_open_a_result(): void
    {
        [$kid, $activity] = $this->child();
        $this->speechSays(60.0);
        $this->actingAs($kid, 'learner');
        $answer = $this->send('learner.activity.record', $activity, 'key-1')->assertOk();

        $other = $this->learner();
        $this->actingAs($other, 'learner')->getJson($answer->json('statusUrl'))->assertNotFound();
        $this->get($answer->json('resultUrl'))->assertNotFound();
    }

    public function test_a_guess_at_an_id_finds_nothing(): void
    {
        [$kid] = $this->child();
        $this->actingAs($kid, 'learner');

        $this->getJson(route('learner.scoring.status', '00000000-0000-0000-0000-000000000000'))->assertNotFound();
        $this->get(route('learner.scoring.result', 'not-an-id'))->assertNotFound();
    }

    public function test_someone_who_is_not_signed_in_is_sent_to_the_sign_in_page(): void
    {
        $this->get(route('learner.scoring.status', '00000000-0000-0000-0000-000000000000'))->assertRedirect(route('learner.login'));
        $this->post(route('learner.diagnostic.record'), [])->assertRedirect(route('learner.login'));
    }

    public function test_the_result_page_can_be_reloaded_and_one_that_is_not_ready_goes_to_the_dashboard(): void
    {
        [$kid, $activity] = $this->child();
        $this->speechSays(60.0);
        $this->actingAs($kid, 'learner');

        $done = $this->send('learner.activity.record', $activity, 'key-1')->assertOk();
        $this->get($done->json('resultUrl'))->assertOk();
        $this->get($done->json('resultUrl'))->assertOk()->assertViewIs('learner.reading-results');
        $this->assertSame(10 + 30, $kid->fresh()->points, 'opening the result again counts nothing');

        Queue::fake();
        $waiting = $this->send('learner.activity.record', $activity, 'key-2')->assertStatus(202);
        $this->get(route('learner.scoring.result', $waiting->json('id')))->assertRedirect(route('learner.dashboard'));
    }

    // ------------------------------------------------------------------ no worker, or a lost one

    public function test_a_waiting_recording_is_scored_by_the_page_that_waits_only_when_no_worker_has_been_seen(): void
    {
        Queue::fake();
        [$kid, $activity] = $this->child();
        $this->speechSays(60.0);
        $this->actingAs($kid, 'learner');

        $answer = $this->send('learner.activity.record', $activity, 'key-1')->assertStatus(202);
        $job = ScoringJob::find($answer->json('id'));
        $job->forceFill(['created_at' => now()->subSeconds(30)])->save();

        // A worker is alive (busy with other children): the page keeps waiting, it never scores inside a web request.
        BackgroundScoring::heartbeat();
        $this->getJson($answer->json('statusUrl'))->assertOk()->assertJsonPath('status', 'queued');
        $this->assertSame(0, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());

        // No worker has been seen for a long time: scored right there so the app still works without one.
        Cache::flush();
        $this->getJson($answer->json('statusUrl'))->assertOk()->assertJsonPath('status', 'done');
        $this->assertSame(1, ReadingSession::where('learner_id', $kid->id)->where('session_type', 'Practice')->count());
        $this->assertSame([], $this->files());
    }

    public function test_a_young_waiting_recording_is_not_scored_by_the_page_even_with_no_worker_seen(): void
    {
        Queue::fake();
        [$kid, $activity] = $this->child();
        $this->actingAs($kid, 'learner');

        $answer = $this->send('learner.activity.record', $activity, 'key-1')->assertStatus(202);

        $this->getJson($answer->json('statusUrl'))->assertOk()->assertJsonPath('status', 'queued');
    }

    public function test_a_recording_nobody_finished_is_given_up_on_and_can_be_sent_again(): void
    {
        Queue::fake();
        [$kid, $activity] = $this->child();
        $this->speechSays(60.0);
        $this->actingAs($kid, 'learner');

        $answer = $this->send('learner.activity.record', $activity, 'lost-key')->assertStatus(202);
        ScoringJob::find($answer->json('id'))->forceFill(['created_at' => now()->subSeconds(ScoringJob::staleAfter() + 10)])->save();

        $this->getJson($answer->json('statusUrl'))->assertOk()->assertJsonPath('status', 'failed')
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Send Again'));
        $this->assertSame([], $this->files(), 'the temporary recording goes when the job is given up on');

        // The page still has the recording and sends it again with the same key.
        Queue::fake([]);
        config(['queue.default' => 'sync']);
        $this->send('learner.activity.record', $activity, 'lost-key')->assertStatus(202);
        $this->assertSame(1, ScoringJob::where('client_key', 'lost-key')->count());
    }

    public function test_a_worker_that_dies_in_the_middle_leaves_nothing_running_for_ever(): void
    {
        [$kid, $activity] = $this->child();
        $job = ScoringJob::create([
            'learner_id' => $kid->id, 'kind' => ScoringJob::READING, 'activity_id' => $activity->id, 'client_key' => 'k',
            'status' => ScoringJob::RUNNING,
        ]);
        $job->forceFill(['created_at' => now()->subSeconds(ScoringJob::staleAfter() + 5)])->save();

        $this->assertTrue($job->fresh()->isStale());
        $this->assertSame(ScoringJob::FAILED, app(BackgroundScoring::class)->refresh($job->fresh())->status);
    }

    // ------------------------------------------------------------------ the other places a child records

    public function test_a_free_practice_try_goes_the_same_way_and_saves_nothing(): void
    {
        [$kid, $activity] = $this->child();
        $this->speechSays(75.0);
        $this->actingAs($kid, 'learner');

        $answer = $this->send('learner.activity.practice', $activity, 'try-1')->assertOk()->assertJsonPath('status', 'done');

        $this->get($answer->json('resultUrl'))->assertOk()->assertViewIs('learner.activity-practice-result')->assertViewHas('triesLeft', 1);
        $this->assertSame(1, ReadingSession::where('learner_id', $kid->id)->count(), 'only the first check session exists; a try is not saved');
        $this->assertSame(10, $kid->fresh()->points);
    }

    public function test_a_practice_try_after_the_tries_are_used_up_tells_the_page_to_move_on(): void
    {
        [$kid, $activity] = $this->child();
        Cache::put("practice-tries:{$kid->id}:{$activity->id}", 2, now()->addHour());
        $this->actingAs($kid, 'learner');

        $this->send('learner.activity.practice', $activity, 'try-3')->assertOk()->assertJsonPath('status', 'redirect')
            ->assertJsonPath('url', route('learner.activity.show', [$activity, 'stage' => 'real']));
        $this->assertSame(0, ScoringJob::count());
    }

    public function test_a_bookshelf_reread_goes_the_same_way_and_stays_free(): void
    {
        [$kid, $activity] = $this->child();
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 80]);
        $this->speechSays(85.0);
        $this->actingAs($kid, 'learner');
        $before = ReadingSession::count();

        $answer = $this->send('learner.bookshelf.reread.submit', $activity, 'reread-1')->assertOk()->assertJsonPath('status', 'done');

        $this->get($answer->json('resultUrl'))->assertOk()->assertViewIs('learner.bookshelf-reread-results');
        $this->assertSame($before, ReadingSession::count());
        $this->assertSame(10, $kid->fresh()->points);
    }

    public function test_the_first_reading_check_advances_once_even_if_the_recording_is_sent_twice(): void
    {
        $kid = $this->learner(['mastery_level' => null, 'reading_rung' => null, 'reading_stage' => 'starting', 'placement_answers' => null]);
        $this->actingAs($kid, 'learner')->get(route('learner.diagnostic.passage'))->assertOk();
        $service = app(LearnerDiagnosticService::class);
        $this->assertSame('letters', $service->diagnosticState($kid)['current_tier']);

        // Five letters of six: the check goes on to the first words.
        Http::fake(['reading.test/analyze' => Http::response($this->analysis(83.33))]);

        $first = $this->postJson(route('learner.diagnostic.record'), ['audio' => $this->audio(), 'client_key' => 'check-1'])->assertOk()->assertJsonPath('status', 'done');
        $again = $this->postJson(route('learner.diagnostic.record'), ['audio' => $this->audio(), 'client_key' => 'check-1'])->assertOk();

        $this->assertSame($first->json('id'), $again->json('id'));
        $this->assertSame(1, $service->diagnosticState($kid)['passages_done'], 'one reading, counted once');
        $this->assertSame(1, ReadingSession::where('learner_id', $kid->id)->count());

        $this->get($first->json('resultUrl'))->assertOk()->assertViewIs('learner.diagnostic-encourage')->assertViewHas('passagesDone', 1);
    }

    public function test_the_finished_check_page_shows_the_child_and_their_level(): void
    {
        $kid = $this->learner(['mastery_level' => null, 'reading_rung' => null, 'reading_stage' => 'starting', 'placement_answers' => null]);
        $this->actingAs($kid, 'learner')->get(route('learner.diagnostic.passage'))->assertOk();
        // Four letters of six ends the check on letters.
        Http::fake(['reading.test/analyze' => Http::response($this->analysis(66.67))]);

        $answer = $this->postJson(route('learner.diagnostic.record'), ['audio' => $this->audio(), 'client_key' => 'end-1'])->assertOk();

        $this->get($answer->json('resultUrl'))->assertOk()->assertViewIs('learner.diagnostic-results')
            ->assertViewHas('learner', fn ($l) => $l instanceof Learner && $l->id === $kid->id)
            ->assertViewHas('finalLevel', 'Beginning');
        $this->assertSame(0, $kid->fresh()->reading_rung);
    }

    // ------------------------------------------------------------------ the pieces

    public function test_the_codec_turns_models_into_plain_json_and_back(): void
    {
        [$kid, $activity] = $this->child();
        $json = OutcomeCodec::encode(['activity' => $activity, 'learner' => $kid, 'n' => 1.0, 'when' => now(), 'list' => [['a' => null, 'b' => true]]]);

        $this->assertStringNotContainsString('Farm Words', $json, 'a model is stored as its class and key, not its columns');
        $back = OutcomeCodec::decode($json);

        $this->assertInstanceOf(Activity::class, $back['activity']);
        $this->assertSame($activity->id, $back['activity']->id);
        $this->assertSame($kid->id, $back['learner']->id);
        $this->assertSame(1.0, $back['n']);
        $this->assertSame([['a' => null, 'b' => true]], $back['list']);
        $this->assertIsString($back['when']);
    }

    public function test_the_codec_will_not_build_anything_but_our_own_models(): void
    {
        $back = OutcomeCodec::decode(json_encode(['x' => ['__model' => 'Illuminate\\Foundation\\Auth\\User', 'id' => 1], 'y' => ['__model' => 'stdClass', 'id' => 1]]));

        $this->assertNull($back['x']);
        $this->assertNull($back['y']);
        $this->assertNull(OutcomeCodec::decode(null));
        $this->assertNull(OutcomeCodec::decode(''));
    }

    public function test_old_results_and_stray_recordings_are_cleaned_up(): void
    {
        [$kid, $activity] = $this->child();
        File::ensureDirectoryExists(ScoringJob::audioFolder());
        file_put_contents(ScoringJob::audioFolder().'/old.webm', 'x');
        touch(ScoringJob::audioFolder().'/old.webm', time() - 7200);
        file_put_contents(ScoringJob::audioFolder().'/new.webm', 'x');
        $old = ScoringJob::create(['learner_id' => $kid->id, 'kind' => 'reading', 'activity_id' => $activity->id, 'client_key' => 'old', 'status' => 'done']);
        $old->forceFill(['created_at' => now()->subHours(30)])->save();
        $new = ScoringJob::create(['learner_id' => $kid->id, 'kind' => 'reading', 'activity_id' => $activity->id, 'client_key' => 'new', 'status' => 'done']);

        app(BackgroundScoring::class)->prune(always: true);

        $this->assertSame(['new.webm'], $this->files());
        $this->assertNull(ScoringJob::find($old->id));
        $this->assertNotNull(ScoringJob::find($new->id));
    }

    public function test_the_status_allowance_is_counted_per_child_not_per_address(): void
    {
        // A class shares one internet address at school: one child using up their asks must not turn the others away.
        [$busy] = $this->child();
        $quiet = $this->learner();
        $statusUrl = route('learner.scoring.status', '00000000-0000-0000-0000-000000000000');

        $this->actingAs($busy, 'learner');
        for ($i = 0; $i < 120; $i++) {
            $this->getJson($statusUrl)->assertNotFound();
        }
        $this->getJson($statusUrl)->assertStatus(429);

        $this->actingAs($quiet, 'learner')->getJson($statusUrl)->assertNotFound();
    }

    // ------------------------------------------------------------------ the page and the container

    public function test_the_recording_page_sends_in_the_background_and_asks_how_it_is_going(): void
    {
        [$kid, $activity] = $this->child();
        $this->actingAs($kid, 'learner');

        $page = $this->get(route('learner.activity.show', ['activity' => $activity, 'stage' => 'real']))->assertOk();

        $page->assertSee('askHowItIsGoing', false)->assertSee('client_key', false)->assertSee("Accept: 'application/json'", false);
        // The old way is only the fallback for a browser without fetch.
        $page->assertSee("form.submit(); return; }", false);
    }

    public function test_erasing_a_child_deletes_their_waiting_recordings_and_jobs(): void
    {
        Queue::fake();
        [$kid, $activity] = $this->child();
        $this->actingAs($kid, 'learner');
        $this->send('learner.activity.record', $activity, 'key-1')->assertStatus(202);
        $this->assertCount(1, $this->files());

        $parentUser = User::create(['first_name' => 'Mom', 'last_name' => 'Cruz', 'email' => 'mom@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Parent']);
        $parent = \App\Models\ParentAccount::create(['user_id' => $parentUser->id]);
        $parent->learners()->attach($kid->id, ['relationship' => 'Mother', 'is_creator' => true]);

        app(\App\Services\DataErasureService::class)->removeChild($parentUser, $kid, 'Passw0rd!');

        $this->assertSame(0, ScoringJob::count());
        $this->assertSame([], $this->files());
    }

    // ------------------------------------------------------------------ the routes that still score inside the request

    public function test_a_request_beyond_the_scoring_slots_is_turned_away_at_once_instead_of_taking_the_last_worker(): void
    {
        config(['scoring.web_slots' => 2]);
        $middleware = new \App\Http\Middleware\LimitScoringWorkers;
        $request = \Illuminate\Http\Request::create('/x', 'POST');
        $ran = 0;
        $next = function () use (&$ran) {
            $ran++;

            return response('ok');
        };

        // Two are already waiting on the speech service.
        Cache::put('scoring-web-slots', 2, now()->addMinutes(2));
        $turnedAway = $middleware->handle($request, $next);

        $this->assertSame(503, $turnedAway->getStatusCode());
        $this->assertSame('unavailable', json_decode($turnedAway->getContent(), true)['status']);
        $this->assertSame('5', $turnedAway->headers->get('Retry-After'));
        $this->assertSame(0, $ran, 'the extra request never reached the speech service');
        $this->assertSame(2, (int) Cache::get('scoring-web-slots'), 'turning one away does not change the count of those still waiting');

        // One finishes: the next goes through, and gives its slot back when it is done.
        Cache::put('scoring-web-slots', 1, now()->addMinutes(2));
        $this->assertSame(200, $middleware->handle($request, $next)->getStatusCode());
        $this->assertSame(1, $ran);
        $this->assertSame(1, (int) Cache::get('scoring-web-slots'));
    }

    public function test_a_slot_is_given_back_even_when_the_request_fails(): void
    {
        $middleware = new \App\Http\Middleware\LimitScoringWorkers;

        try {
            $middleware->handle(\Illuminate\Http\Request::create('/x', 'POST'), fn () => throw new \RuntimeException('boom'));
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, (int) Cache::get('scoring-web-slots'));
    }

    public function test_the_counter_never_goes_below_zero_if_it_expired_while_a_request_ran(): void
    {
        $middleware = new \App\Http\Middleware\LimitScoringWorkers;

        $middleware->handle(\Illuminate\Http\Request::create('/x', 'POST'), function () {
            Cache::forget('scoring-web-slots');

            return response('ok');
        });

        $this->assertSame(0, (int) Cache::get('scoring-web-slots'));
    }

    public function test_only_the_routes_that_score_inside_the_request_carry_the_guard(): void
    {
        $guarded = fn (string $name) => in_array('scoring.slots', \Illuminate\Support\Facades\Route::getRoutes()->getByName($name)->gatherMiddleware(), true);

        foreach (['api.learner.diagnostic.record', 'api.learner.activities.record', 'api.learner.activities.practice', 'api.learner.bookshelf.reread',
            'api.learner.games.check-text', 'api.learner.games.check-word', 'learner.games.check-text', 'learner.games.check-word'] as $name) {
            $this->assertTrue($guarded($name), "$name scores inside the request and must be guarded");
        }

        // The website's reading screens hand the recording to a background worker and never hold a web worker.
        foreach (['learner.activity.record', 'learner.activity.practice', 'learner.diagnostic.record', 'learner.bookshelf.reread.submit'] as $name) {
            $this->assertFalse($guarded($name), "$name uses the background and must not be limited by the slots");
        }
    }
}

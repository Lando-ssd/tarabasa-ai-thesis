<?php

namespace Tests\Feature;

use App\Jobs\GenerateActivitiesJob;
use App\Models\Activity;
use App\Models\ActivityGeneration;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ActivityAiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * "Generate activities" is written in the background: the AI takes one to several minutes, far too
 * long to hold a web request open. These check the request is saved and queued at once, written by
 * the job (charging one credit only when the activities are saved), followed by the Activities page,
 * and that a request can never be lost, run twice, or block the teacher for good.
 */
class ActivityGenerationBackgroundTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function teacher(string $status = 'Active', int $credits = 2): array
    {
        $n = ++$this->seq;
        $user = User::create(['first_name' => 'Ana', 'last_name' => "Reyes{$n}", 'email' => "bg{$n}@example.com", 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'Rizal ES', 'employee_id' => "B{$n}", 'status' => $status, 'free_generation_credits_remaining' => $credits]);

        return [$user, $teacher];
    }

    private function request(Teacher $teacher, array $o = []): ActivityGeneration
    {
        return ActivityGeneration::create($o + [
            'teacher_id' => $teacher->id, 'status' => ActivityGeneration::QUEUED, 'grade_level' => 'Grade 2',
            'competency' => 'foundational_reading', 'activity_type' => 'word_reading', 'topic' => 'Animals',
            'levels' => ['Easy' => 0, 'Medium' => 2, 'Hard' => 1],
        ]);
    }

    private function bundle(): array
    {
        $variant = fn (string $tier, string $label) => [
            'difficulty' => strtolower($tier), 'variant_label' => $label, 'title' => "{$tier} Animals {$label}", 'instructions' => 'Read the words.',
            'display_text' => 'cat dog pig hen cow bat rat', 'reference_text' => 'cat dog pig hen cow bat rat', 'word_count' => 7,
            'target_skills' => ['Reading words'], 'reading_features' => ['Familiar words'], 'follow_up_questions' => [],
        ];

        return [
            'generation_id' => 'gen-bg', 'bundle_title' => 'Grade 2 Foundational Reading', 'competency_label' => 'Foundational Reading', 'total_activities' => 6,
            'levels' => [
                'easy' => [$variant('Easy', 'A'), $variant('Easy', 'B')],
                'medium' => [$variant('Medium', 'A'), $variant('Medium', 'B')],
                'hard' => [$variant('Hard', 'A'), $variant('Hard', 'B')],
            ],
        ];
    }

    private function fakeGenerator(): void
    {
        config(['services.activity_ai.url' => 'https://gen.test', 'services.activity_ai.key' => 'k']);
        Http::fake(['gen.test/*' => Http::response($this->bundle())]);
    }

    private function form(): array
    {
        return ['grade_level' => 'Grade 2', 'competency' => 'foundational_reading', 'activity_type' => 'word_reading', 'levels' => ['Easy' => 0, 'Medium' => 2, 'Hard' => 1]];
    }

    // -------------------------------------------------------------- the request

    public function test_asking_saves_and_queues_the_request_and_answers_at_once(): void
    {
        Queue::fake();
        $this->fakeGenerator();
        [$user, $teacher] = $this->teacher();
        $this->actingAs($user);

        $this->post(route('teacher.activities.generate'), $this->form() + ['topic' => 'Animals'])
            ->assertRedirect(route('teacher.activities.index', ['view' => 'board']))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'The AI is writing your 3 activities'));

        $generation = ActivityGeneration::firstOrFail();
        $this->assertSame(ActivityGeneration::QUEUED, $generation->status);
        $this->assertSame(['Easy' => 0, 'Medium' => 2, 'Hard' => 1], $generation->levels);
        Queue::assertPushed(GenerateActivitiesJob::class, fn ($job) => $job->generationId === $generation->id);

        // Nothing is asked of the AI, nothing is charged and nothing is written until the job runs.
        Http::assertNothingSent();
        $this->assertSame(0, Activity::count());
        $this->assertSame(2, $teacher->fresh()->free_generation_credits_remaining);

        // The Activities page follows it.
        $this->get(route('teacher.activities.index'))
            ->assertOk()
            ->assertSee('id="genStatus"', false)
            ->assertSee('The AI is writing 3 activities')
            ->assertSee(route('teacher.activities.generation', $generation), false);
    }

    public function test_one_request_at_a_time(): void
    {
        Queue::fake();
        [$user, $teacher] = $this->teacher();
        $this->request($teacher, ['status' => ActivityGeneration::RUNNING]);
        $this->actingAs($user);

        $this->post(route('teacher.activities.generate'), $this->form())->assertSessionHasErrors('generate');

        $this->assertSame(1, ActivityGeneration::count());
        Queue::assertNothingPushed();
    }

    public function test_a_request_that_was_lost_never_blocks_the_teacher_for_good(): void
    {
        Queue::fake();
        [$user, $teacher] = $this->teacher();
        $lost = $this->request($teacher, ['status' => ActivityGeneration::RUNNING]);
        ActivityGeneration::whereKey($lost->id)->update(['created_at' => now()->subMinutes(ActivityGeneration::LOST_AFTER_MINUTES + 1)]);
        $this->actingAs($user);

        $this->post(route('teacher.activities.generate'), $this->form())->assertSessionHasNoErrors();

        $this->assertSame(ActivityGeneration::FAILED, $lost->fresh()->status);
        $this->assertStringContainsString('Nothing was charged', $lost->fresh()->message);
        $this->assertSame(2, ActivityGeneration::count());
    }

    // ------------------------------------------------------------------ the job

    public function test_the_job_writes_only_the_levels_asked_for_and_charges_one_credit(): void
    {
        $this->fakeGenerator();
        [, $teacher] = $this->teacher();
        $generation = $this->request($teacher, ['teacher_notes' => 'Use animals.']);

        (new GenerateActivitiesJob($generation->id))->handle(app(ActivityAiClient::class));

        $generation->refresh();
        $this->assertSame(ActivityGeneration::DONE, $generation->status);
        $this->assertSame(3, $generation->created_count);
        $this->assertSame('3 drafts added to To review: 2 Medium, 1 Hard.', $generation->message);
        $this->assertNotNull($generation->finished_at);

        $this->assertSame(2, Activity::where('difficulty_tier', 'Medium')->count());
        $this->assertSame(1, Activity::where('difficulty_tier', 'Hard')->count());
        $this->assertSame(0, Activity::where('difficulty_tier', 'Easy')->count());
        $this->assertSame(['Draft'], Activity::pluck('status')->unique()->values()->all());
        $this->assertSame($teacher->id, Activity::first()->created_by_teacher_id);
        $this->assertSame('Use animals.', Activity::first()->teacher_notes);
        $this->assertSame(1, $teacher->fresh()->free_generation_credits_remaining);

        // One call, asking for the most wanted in any one level.
        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => $r['variants_per_level'] === 2 && $r['grade'] === 2 && $r['topic'] === 'Animals');
    }

    public function test_a_failed_request_costs_nothing_and_says_so(): void
    {
        config(['services.activity_ai.url' => 'https://gen.test', 'services.activity_ai.key' => 'k']);
        Http::fake(['gen.test/*' => Http::response(['detail' => 'boom'], 500)]);
        [, $teacher] = $this->teacher();
        $generation = $this->request($teacher);

        (new GenerateActivitiesJob($generation->id))->handle(app(ActivityAiClient::class));

        $generation->refresh();
        $this->assertSame(ActivityGeneration::FAILED, $generation->status);
        $this->assertStringEndsWith('Nothing was charged.', $generation->message);
        $this->assertSame(0, Activity::count());
        $this->assertSame(2, $teacher->fresh()->free_generation_credits_remaining);
    }

    public function test_a_request_is_never_written_or_charged_twice(): void
    {
        $this->fakeGenerator();
        [, $teacher] = $this->teacher();
        $generation = $this->request($teacher);

        (new GenerateActivitiesJob($generation->id))->handle(app(ActivityAiClient::class));
        (new GenerateActivitiesJob($generation->id))->handle(app(ActivityAiClient::class));

        Http::assertSentCount(1);
        $this->assertSame(3, Activity::count());
        $this->assertSame(1, $teacher->fresh()->free_generation_credits_remaining);
    }

    public function test_the_job_checks_the_credit_again_when_it_runs(): void
    {
        $this->fakeGenerator();
        [, $teacher] = $this->teacher('Active', 0);
        $generation = $this->request($teacher);

        (new GenerateActivitiesJob($generation->id))->handle(app(ActivityAiClient::class));

        $this->assertSame(ActivityGeneration::FAILED, $generation->fresh()->status);
        Http::assertNothingSent();
        $this->assertSame(0, Activity::count());
    }

    public function test_a_job_that_dies_marks_the_request_failed(): void
    {
        [, $teacher] = $this->teacher();
        $generation = $this->request($teacher, ['status' => ActivityGeneration::RUNNING]);

        (new GenerateActivitiesJob($generation->id))->failed(new \RuntimeException('killed'));

        $this->assertSame(ActivityGeneration::FAILED, $generation->fresh()->status);
        $this->assertSame(2, $teacher->fresh()->free_generation_credits_remaining);
    }

    // ------------------------------------------------------ following the request

    public function test_the_status_says_how_far_a_request_has_got(): void
    {
        Queue::fake();
        [$user, $teacher] = $this->teacher();
        $generation = $this->request($teacher, ['status' => ActivityGeneration::RUNNING, 'started_at' => now()->subSeconds(30)]);
        $this->actingAs($user);

        $this->getJson(route('teacher.activities.generation', $generation))
            ->assertOk()
            ->assertJson(['id' => $generation->id, 'status' => 'Running', 'active' => true, 'total' => 3, 'summary' => '2 Medium, 1 Hard']);

        $generation->update(['status' => ActivityGeneration::DONE, 'message' => '3 drafts added to To review: 2 Medium, 1 Hard.']);
        $this->getJson(route('teacher.activities.generation', $generation))
            ->assertOk()
            ->assertJson(['status' => 'Done', 'active' => false, 'message' => '3 drafts added to To review: 2 Medium, 1 Hard.']);
    }

    public function test_only_the_owner_can_follow_or_dismiss_a_request(): void
    {
        [, $teacher] = $this->teacher();
        [$other] = $this->teacher();
        $generation = $this->request($teacher, ['status' => ActivityGeneration::DONE, 'message' => 'x']);
        $this->actingAs($other);

        $this->getJson(route('teacher.activities.generation', $generation))->assertForbidden();
        $this->postJson(route('teacher.activities.generation.dismiss', $generation))->assertForbidden();
        $this->assertNull($generation->fresh()->acknowledged_at);
    }

    public function test_a_request_nobody_picked_up_is_written_by_the_page_that_is_watching_it(): void
    {
        $this->fakeGenerator();
        [$user, $teacher] = $this->teacher();
        $generation = $this->request($teacher);
        $this->actingAs($user);

        // Just asked for: still waiting for the worker, so the page does not step in yet.
        $this->getJson(route('teacher.activities.generation', $generation))->assertJson(['status' => 'Queued']);
        Http::assertNothingSent();

        // Nobody has taken it for a while (the worker is not running): the watching page writes it.
        ActivityGeneration::whereKey($generation->id)->update(['created_at' => now()->subSeconds(ActivityGeneration::PICKUP_WAIT_SECONDS + 5)]);
        $this->getJson(route('teacher.activities.generation', $generation))->assertJson(['status' => 'Done', 'active' => false]);

        $this->assertSame(3, Activity::count());
        $this->assertSame(1, $teacher->fresh()->free_generation_credits_remaining);

        // And watching again never writes it a second time.
        $this->getJson(route('teacher.activities.generation', $generation))->assertJson(['status' => 'Done']);
        Http::assertSentCount(1);
    }

    public function test_dismissing_a_finished_request_removes_the_notice_but_not_a_running_one(): void
    {
        Queue::fake();
        [$user, $teacher] = $this->teacher();
        $finished = $this->request($teacher, ['status' => ActivityGeneration::FAILED, 'message' => 'It did not work. Nothing was charged.']);
        $this->actingAs($user);

        $this->get(route('teacher.activities.index'))
            ->assertSee('The AI could not finish')
            ->assertSee('It did not work. Nothing was charged.')
            ->assertSee('Try again');

        $this->postJson(route('teacher.activities.generation.dismiss', $finished))->assertOk();
        $this->assertNotNull($finished->fresh()->acknowledged_at);
        $this->get(route('teacher.activities.index'))->assertDontSee('id="genStatus"', false);

        $running = $this->request($teacher, ['status' => ActivityGeneration::RUNNING]);
        $this->postJson(route('teacher.activities.generation.dismiss', $running))->assertOk();
        $this->assertNull($running->fresh()->acknowledged_at);
        $this->get(route('teacher.activities.index'))->assertSee('id="genStatus"', false);
    }

    // ---------------------------------------------------- no credits: a way forward

    public function test_a_teacher_with_no_credits_is_shown_how_to_earn_more(): void
    {
        [$user, $teacher] = $this->teacher('Active', 0);
        $this->actingAs($user);

        // Nothing to share yet: told to approve one first.
        $this->get(route('teacher.activities.index'))
            ->assertSee('No free credits left')
            ->assertSee('Approve a draft and share it to the Repository to earn 2 more credits.')
            ->assertDontSee('>Share one<', false);

        // An approved activity that has not been shared: a button straight to it.
        Activity::create([
            'created_by_teacher_id' => $teacher->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'ai_difficulty_tier' => 'Easy', 'title' => 'Easy Words', 'instructions' => 'Read.',
            'passage_text' => 'cat dog', 'word_count' => 2, 'status' => 'Approved',
        ]);

        $this->get(route('teacher.activities.index'))
            ->assertSee('You have 1 approved activity not shared yet')
            ->assertSee('>Share one<', false)
            ->assertSee(e(route('teacher.activities.index', ['view' => 'list', 'status' => 'Approved'])), false);
    }
}

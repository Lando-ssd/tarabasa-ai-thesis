<?php

namespace Tests\Feature;

use App\Jobs\WakeServiceJob;
use App\Models\Learner;
use App\Support\LearnerCode;
use App\Support\ServiceWake;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The recording page asks "is the scoring service awake?" a few seconds apart BEFORE it sends the recording, so the wait
 * for a sleeping free service happens on the child's screen (with the recording safe) and not inside a web request that
 * holds one of the host's four workers. Each ask must answer within a few seconds.
 */
class RecordingReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.reading_ai.url' => 'https://reader.test',
            'services.adaptive_recommender.url' => null,
            'services.activity_ai.url' => null,
            'queue.default' => 'database', // so a wake-up is a real queued job (faked below), not run inside the request
        ]);
        Cache::flush();
        Queue::fake();
    }

    private function kid(): Learner
    {
        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => 'Mia', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => null, 'reading_stage' => 'starting', 'placement_answers' => null,
        ]);
    }

    public function test_a_guest_is_sent_to_the_learner_sign_in(): void
    {
        $this->get(route('learner.ready'))->assertRedirect(route('learner.login'));
    }

    public function test_with_no_checker_configured_there_is_nothing_to_wait_for(): void
    {
        config(['services.reading_ai.url' => null]);
        Http::fake();

        $this->actingAs($this->kid(), 'learner')->getJson(route('learner.ready'))->assertOk()->assertExactJson(['ready' => true]);

        Http::assertNothingSent();
    }

    public function test_a_checker_seen_awake_a_moment_ago_is_ready_without_asking_it_again(): void
    {
        Http::fake();
        ServiceWake::markAwake('reader');

        $this->actingAs($this->kid(), 'learner')->getJson(route('learner.ready'))->assertOk()->assertExactJson(['ready' => true]);

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_a_poll_never_asks_the_checker_itself_it_only_starts_the_wake_up_once(): void
    {
        // The host has four web workers and removes the whole site when its own health check is not answered within 5 seconds, so a
        // poll that waited on the checker (as the first version did, up to 4 seconds each) keeps a worker busy. It must cost nothing.
        Http::fake();
        $kid = $this->kid();

        $this->actingAs($kid, 'learner');
        $this->getJson(route('learner.ready'))->assertOk()->assertExactJson(['ready' => false]);
        $this->getJson(route('learner.ready'))->assertOk()->assertExactJson(['ready' => false]);
        $this->getJson(route('learner.ready'))->assertOk()->assertExactJson(['ready' => false]);

        Http::assertNothingSent();
        $this->assertFalse(ServiceWake::isKnownAwake('reader'));
        Queue::assertPushed(WakeServiceJob::class, 1); // three polls, one background wake-up
    }

    public function test_when_the_background_wake_up_hears_the_checker_the_next_poll_is_ready(): void
    {
        config(['services.reading_ai.ready_wait' => 10]);
        Http::fake(['reader.test/health' => Http::response(['status' => 'ok', 'model_loaded' => true])]);
        $kid = $this->kid();

        $this->actingAs($kid, 'learner')->getJson(route('learner.ready'))->assertOk()->assertExactJson(['ready' => false]);

        // What the queue worker does with the job it was given: stay connected to the health page and remember the answer.
        (new WakeServiceJob('reader'))->handle();

        $this->getJson(route('learner.ready'))->assertOk()->assertExactJson(['ready' => true]);
        $this->assertTrue(ServiceWake::isKnownAwake('reader'));
    }

    public function test_a_checker_that_never_answers_keeps_the_page_polling_and_is_never_an_error(): void
    {
        config(['services.reading_ai.ready_wait' => 1]);
        Http::fake(['reader.test/health' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);
        $kid = $this->kid();

        $this->actingAs($kid, 'learner')->getJson(route('learner.ready'))->assertOk()->assertExactJson(['ready' => false]);
        (new WakeServiceJob('reader'))->handle(); // does not come up in time, and says nothing
        $this->getJson(route('learner.ready'))->assertOk()->assertExactJson(['ready' => false]);
    }

    public function test_one_recording_may_hold_a_web_worker_for_45_seconds_not_100(): void
    {
        // The host removes the site from service after 15 seconds of an unanswered health check. The checker answers in 5 to 10 seconds
        // once awake, and the page already waited for a sleeping checker without holding a worker, so the server's own wait is short.
        $this->assertSame(45, (int) config('services.reading_ai.max_hold'));
    }

    public function test_the_recording_page_asks_this_address_before_it_sends_the_recording(): void
    {
        config(['services.reading_ai.url' => null]);
        $kid = $this->kid();

        $page = $this->actingAs($kid, 'learner')->get(route('learner.diagnostic.passage'))->assertOk();

        $page->assertSee('learner\/ready', false); // the address, as the page's script holds it
        $page->assertSee('checkerIsAwake', false);
        $page->assertSee('Waking up the reading checker', false);
    }
}

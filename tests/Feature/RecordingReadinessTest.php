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

    public function test_a_sleeping_checker_that_answers_its_health_page_is_ready_and_remembered(): void
    {
        Http::fake(['reader.test/health' => Http::response(['status' => 'ok', 'model_loaded' => true])]);

        $this->actingAs($this->kid(), 'learner')->getJson(route('learner.ready'))->assertOk()->assertExactJson(['ready' => true]);

        $this->assertTrue(ServiceWake::isKnownAwake('reader'), 'the next ask (and the recording itself) need not ask again');
    }

    public function test_a_checker_that_is_still_waking_is_not_ready_and_the_wake_up_is_started_once(): void
    {
        Http::fake(['reader.test/health' => Http::response('<html>Service waking up</html>', 503)]);
        $kid = $this->kid();

        $this->actingAs($kid, 'learner');
        $this->getJson(route('learner.ready'))->assertOk()->assertExactJson(['ready' => false]);
        $this->getJson(route('learner.ready'))->assertOk()->assertExactJson(['ready' => false]);

        $this->assertFalse(ServiceWake::isKnownAwake('reader'));
        Queue::assertPushed(WakeServiceJob::class, 1); // two asks, one background wake-up
    }

    public function test_a_checker_that_does_not_answer_at_all_is_not_ready_and_never_an_error(): void
    {
        Http::fake(['reader.test/health' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out')]);

        $this->actingAs($this->kid(), 'learner')->getJson(route('learner.ready'))->assertOk()->assertExactJson(['ready' => false]);
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

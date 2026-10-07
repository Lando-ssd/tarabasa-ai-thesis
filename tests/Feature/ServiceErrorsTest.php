<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\ServiceFailure;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ActivityAiClient;
use App\Services\ReadingAiClient;
use App\Support\ServiceReply;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * On the live site every failed generation and reading check said the same vague "the service returned an
 * unexpected error", so nobody could tell a sleeping service from a rejected request from a rate limit.
 * These pin down that each kind of answer now gives a clear sentence with its HTTP status, that the brief
 * hosting hiccups (502, 503, 504, 429) are asked again, and that real mistakes are NOT asked again.
 */
class ServiceErrorsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.activity_ai.url' => 'https://generator.test',
            'services.activity_ai.key' => 'k',
            'services.reading_ai.url' => 'https://reader.test',
            'services.retry_pause' => 0, // no real waiting in tests
        ]);
    }

    private int $replyNumber = 0;

    /** A real Response object with the given status and body (each call uses its own fake host). */
    private function reply(int $status, mixed $body, array $headers = []): \Illuminate\Http\Client\Response
    {
        $host = 'x'.(++$this->replyNumber).'.test';
        Http::fake(["{$host}/*" => Http::response($body, $status, $headers)]);

        return Http::get("https://{$host}/y");
    }

    /** How many requests went to one fake host (other lookups the app makes on the side are not counted). */
    private function sentTo(string $host): int
    {
        return collect(Http::recorded())->filter(fn ($pair) => str_contains($pair[0]->url(), $host))->count();
    }

    // ----- how each kind of answer is described

    public function test_the_service_own_message_is_shown_with_its_status(): void
    {
        $text = ServiceReply::message($this->reply(401, ['detail' => 'Invalid or missing X-App-Key.']), 'Activity generation', 'waking');
        $this->assertSame('Activity generation failed: Invalid or missing X-App-Key. (error 401)', $text);

        $nested = ServiceReply::message($this->reply(502, ['detail' => ['message' => 'The model is overloaded', 'failed_level' => 'easy']]), 'Activity generation', 'waking');
        $this->assertStringContainsString('The model is overloaded', $nested);
    }

    public function test_a_502_the_service_wrote_itself_is_shown_and_not_repeated(): void
    {
        Http::fake(['generator.test/generate-bundle' => Http::response(['detail' => ['message' => 'The AI model failed on the hard level', 'failed_level' => 'hard']], 502)]);

        try {
            app(ActivityAiClient::class)->generateBundle(['grade' => 1], 5);
            $this->fail('should have thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('The AI model failed on the hard level', $e->getMessage());
            $this->assertStringNotContainsString('waking up', $e->getMessage());
        }

        $this->assertSame(1, $this->sentTo('generator.test/generate-bundle'), 'its own failure answer is not asked again');
    }

    public function test_a_rejected_request_names_the_field_instead_of_saying_unexpected(): void
    {
        $text = ServiceReply::message($this->reply(422, ['detail' => [['type' => 'string_too_long', 'loc' => ['body', 'teacher_notes'], 'msg' => 'String should have at most 1000 characters']]]), 'Activity generation', 'waking');

        $this->assertStringContainsString('teacher_notes', $text);
        $this->assertStringContainsString('at most 1000 characters', $text);
        $this->assertStringContainsString('error 422', $text);
        $this->assertStringNotContainsString('unexpected', $text);
    }

    public function test_a_rate_limit_answer_is_understood(): void
    {
        $text = ServiceReply::message($this->reply(429, ['error' => 'Rate limit exceeded: 5 per 1 minute']), 'Reading check', 'The reading checker is waking up or busy right now');

        $this->assertStringContainsString('429', $text);
        $this->assertStringContainsString('waking up or busy', $text);
    }

    public function test_a_web_page_from_the_hosting_is_reported_by_status_not_swallowed(): void
    {
        $page = $this->reply(503, '<html><body><h1>Service Unavailable</h1><p>Waking up</p></body></html>', ['Content-Type' => 'text/html']);

        $this->assertTrue(ServiceReply::isTransient($page));
        $this->assertStringContainsString('error 503', ServiceReply::message($page, 'Activity generation', 'The activity generator is waking up or busy right now'));
        $this->assertSame('Service Unavailable Waking up', ServiceReply::snippet($page), 'the log keeps a readable version of the page');
    }

    public function test_an_empty_error_still_gives_the_status_and_says_there_was_no_explanation(): void
    {
        $text = ServiceReply::message($this->reply(500, ''), 'Reading check', 'waking');

        $this->assertStringContainsString('error 500', $text);
        $this->assertStringContainsString('no explanation', $text);
    }

    public function test_only_hosting_hiccups_count_as_temporary(): void
    {
        foreach ([502, 503, 504, 429, 408] as $status) {
            $this->assertTrue(ServiceReply::isTransient($this->reply($status, '')), (string) $status);
        }
        foreach ([400, 401, 403, 404, 413, 422, 500] as $status) {
            $this->assertFalse(ServiceReply::isTransient($this->reply($status, '')), (string) $status);
        }
    }

    // ----- the generator client

    public function test_the_generator_is_asked_again_after_a_temporary_error_and_then_succeeds(): void
    {
        Http::fake(['generator.test/generate-bundle' => Http::sequence()
            ->push('<html>Bad gateway</html>', 502)
            ->push('<html>Service waking up</html>', 503)
            ->push(['total_activities' => 3, 'levels' => ['easy' => []]], 200),
        ]);

        $data = app(ActivityAiClient::class)->generateBundle(['grade' => 1], 5);

        $this->assertSame(3, $data['total_activities']);
        $this->assertSame(3, $this->sentTo('generator.test/generate-bundle'));
    }

    public function test_the_generator_gives_up_after_four_temporary_errors_and_says_why(): void
    {
        Http::fake(['generator.test/generate-bundle' => Http::response('<html>Service waking up</html>', 503)]);

        try {
            app(ActivityAiClient::class)->generateBundle(['grade' => 1], 5);
            $this->fail('should have thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('waking up or busy', $e->getMessage());
            $this->assertStringContainsString('error 503', $e->getMessage());
        }

        $this->assertSame(4, $this->sentTo('generator.test/generate-bundle'));
    }

    public function test_a_real_mistake_in_the_request_is_not_repeated(): void
    {
        Http::fake(['generator.test/generate-bundle' => Http::response(['detail' => [['loc' => ['body', 'variants_per_level'], 'msg' => 'Input should be less than or equal to 5']]], 422)]);

        try {
            app(ActivityAiClient::class)->generateBundle(['grade' => 1], 5);
            $this->fail('should have thrown');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('variants_per_level', $e->getMessage());
            $this->assertStringContainsString('error 422', $e->getMessage());
        }

        $this->assertSame(1, $this->sentTo('generator.test/generate-bundle'));
    }

    public function test_a_wrong_key_is_reported_once_with_the_services_own_words(): void
    {
        Http::fake(['generator.test/generate-bundle' => Http::response(['detail' => 'Invalid or missing X-App-Key.'], 401)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid or missing X-App-Key.');

        try {
            app(ActivityAiClient::class)->generateBundle(['grade' => 1], 5);
        } finally {
            $this->assertSame(1, $this->sentTo('generator.test/generate-bundle'));
        }
    }

    public function test_the_background_job_waits_for_the_sleeping_generator_before_the_real_request(): void
    {
        Http::fake([
            'generator.test/health' => Http::sequence()->push('starting', 503)->push('starting', 503)->push(['status' => 'ok'], 200),
        ]);

        $this->assertTrue(app(ActivityAiClient::class)->awaitReady(30));
        $this->assertSame(3, $this->sentTo('generator.test/health'));
    }

    public function test_waiting_for_the_generator_never_throws_when_it_does_not_come_up(): void
    {
        Http::fake(['generator.test/health' => Http::response('down', 503)]);

        $this->assertFalse(app(ActivityAiClient::class)->awaitReady(1));
    }

    // ----- the reading checker

    private function activity(): Activity
    {
        $user = User::create(['first_name' => 'T', 'last_name' => 'N', 'email' => 't@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $teacher = Teacher::create(['user_id' => $user->id, 'school_name' => 'S', 'employee_id' => 'E1', 'status' => 'Active', 'free_generation_credits_remaining' => 1]);

        return Activity::create([
            'created_by_teacher_id' => $teacher->id, 'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'title' => 'Sun', 'instructions' => 'Read.', 'passage_text' => 'the sun is hot', 'reference_text' => 'the sun is hot', 'status' => 'Approved',
        ]);
    }

    private function goodAnswer(): array
    {
        return ['accuracy' => ['accuracy_score' => 100, 'spoken_word_count' => 4, 'word_feedback' => [
            ['reference' => 'the', 'spoken' => 'the', 'status' => 'correct'], ['reference' => 'sun', 'spoken' => 'sun', 'status' => 'correct'],
            ['reference' => 'is', 'spoken' => 'is', 'status' => 'correct'], ['reference' => 'hot', 'spoken' => 'hot', 'status' => 'correct'],
        ], 'substitutions' => 0, 'insertions' => 0, 'deletions' => 0]];
    }

    public function test_a_recording_is_sent_again_after_a_temporary_error_with_the_file_attached_each_time(): void
    {
        Http::fake(['reader.test/analyze' => Http::sequence()->push('<html>Service waking up</html>', 503)->push($this->goodAnswer(), 200)]);

        $outcome = app(ReadingAiClient::class)->analyze(UploadedFile::fake()->create('recording.webm', 20, 'audio/webm'), $this->activity());

        $this->assertFalse($outcome['unclear']);
        $this->assertSame(2, $this->sentTo('reader.test/analyze'));
        Http::assertSent(fn (Request $r) => $r->hasFile('file') && $r->url() === 'https://reader.test/analyze');
    }

    public function test_the_reading_check_says_the_status_when_the_checker_stays_down(): void
    {
        Http::fake(['reader.test/analyze' => Http::response('<html>Bad gateway</html>', 502)]);

        try {
            app(ReadingAiClient::class)->analyze(UploadedFile::fake()->create('recording.webm', 20, 'audio/webm'), $this->activity());
            $this->fail('should have thrown');
        } catch (ValidationException $e) {
            $message = $e->errors()['audio'][0];
            $this->assertStringContainsString('waking up or busy', $message);
            $this->assertStringContainsString('error 502', $message);
            $this->assertStringNotContainsString('unexpected', $message);
        }

        $this->assertSame(4, $this->sentTo('reader.test/analyze'));
    }

    public function test_a_rejected_recording_request_is_not_repeated_and_is_not_called_unclear(): void
    {
        Http::fake(['reader.test/analyze' => Http::response(['detail' => [['loc' => ['body', 'competency_code'], 'msg' => 'Field required']]], 422)]);

        try {
            app(ReadingAiClient::class)->analyze(UploadedFile::fake()->create('recording.webm', 20, 'audio/webm'), $this->activity());
            $this->fail('should have thrown');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('competency_code', $e->errors()['audio'][0]);
        }

        $this->assertSame(1, $this->sentTo('reader.test/analyze'));
    }

    public function test_silent_audio_is_still_reported_as_unclear_and_not_retried(): void
    {
        Http::fake(['reader.test/analyze' => Http::response(['detail' => 'Audio is silent or nearly silent.'], 422)]);

        $outcome = app(ReadingAiClient::class)->analyze(UploadedFile::fake()->create('recording.webm', 20, 'audio/webm'), $this->activity());

        $this->assertTrue($outcome['unclear']);
        $this->assertSame(1, $this->sentTo('reader.test/analyze'));
    }

    // ----- waiting politely after a "slow down", and the diary of what went wrong

    public function test_a_slow_down_waits_as_long_as_the_host_asks_but_never_more_than_the_cap(): void
    {
        $this->assertSame(12, ServiceReply::pause($this->reply(429, 'x', ['Retry-After' => '12']), 1, 5));
        $this->assertSame(30, ServiceReply::pause($this->reply(429, 'x', ['Retry-After' => '600']), 1, 5), 'capped so a child is never left waiting for minutes');
        $this->assertSame(1, ServiceReply::pause($this->reply(429, 'x', ['Retry-After' => gmdate('D, d M Y H:i:s \G\M\T', time() + 1)]), 1, 5), 'a date works too');
    }

    public function test_a_slow_down_without_a_stated_wait_waits_twice_as_long_as_a_waking_service(): void
    {
        $slowDown = $this->reply(429, 'x');
        $waking = $this->reply(503, 'x');

        $this->assertSame([10, 20, 30], [ServiceReply::pause($slowDown, 1, 5), ServiceReply::pause($slowDown, 2, 5), ServiceReply::pause($slowDown, 3, 5)]);
        $this->assertSame([5, 10, 15], [ServiceReply::pause($waking, 1, 5), ServiceReply::pause($waking, 2, 5), ServiceReply::pause($waking, 3, 5)]);
        $this->assertSame(0, ServiceReply::pause($waking, 3, 0), 'the test setting turns waiting off');
    }

    public function test_a_failed_reading_check_is_written_to_the_diary_with_everything_the_checker_said(): void
    {
        Http::fake(['reader.test/analyze' => Http::response('<html><body>Too Many Requests</body></html>', 429, ['Content-Type' => 'text/html'])]);

        try {
            app(ReadingAiClient::class)->analyze(UploadedFile::fake()->create('recording.webm', 20, 'audio/webm'), $this->activity());
            $this->fail('should have thrown');
        } catch (ValidationException) {
        }

        $row = ServiceFailure::first();
        $this->assertNotNull($row);
        $this->assertSame('reader', $row->service);
        $this->assertSame(429, $row->status);
        $this->assertSame('429,429,429,429', $row->trail, 'one status for every try, so the team can see it was not a single unlucky call');
        $this->assertStringContainsString('text/html', $row->content_type);
        $this->assertStringContainsString('error 429', $row->what);
        $this->assertStringContainsString('Too Many Requests', $row->body);
        $this->assertSame(1, ServiceFailure::count(), 'one row for the whole call, not one per try');
    }

    public function test_a_reading_check_that_works_leaves_nothing_in_the_diary(): void
    {
        Http::fake(['reader.test/analyze' => Http::sequence()->push('<html>waking</html>', 503)->push($this->goodAnswer(), 200)]);

        app(ReadingAiClient::class)->analyze(UploadedFile::fake()->create('recording.webm', 20, 'audio/webm'), $this->activity());

        $this->assertSame(0, ServiceFailure::count(), 'a hiccup that the retry fixed is not a problem worth the team attention');
    }

    public function test_a_checker_that_cannot_be_reached_is_written_to_the_diary_without_a_status(): void
    {
        Http::fake(['reader.test/analyze' => fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: timed out')]);

        try {
            app(ReadingAiClient::class)->analyze(UploadedFile::fake()->create('recording.webm', 20, 'audio/webm'), $this->activity());
            $this->fail('should have thrown');
        } catch (ValidationException) {
        }

        $row = ServiceFailure::first();
        $this->assertSame('reader', $row->service);
        $this->assertNull($row->status);
        $this->assertStringContainsString('timed out', $row->body);
    }

    public function test_a_failed_generation_and_a_failed_recommendation_are_written_to_the_diary_too(): void
    {
        Http::fake(['generator.test/generate-bundle' => Http::response(['detail' => 'Invalid or missing X-App-Key.'], 401)]);

        try {
            app(ActivityAiClient::class)->generateBundle(['grade' => 1], 5);
            $this->fail('should have thrown');
        } catch (\RuntimeException) {
        }

        $row = ServiceFailure::where('service', 'generator')->first();
        $this->assertSame(401, $row->status);
        $this->assertStringContainsString('X-App-Key', $row->body);

        config(['services.adaptive_recommender.url' => 'https://recommender.test', 'services.adaptive_recommender.key' => 'k']);
        Http::fake(['recommender.test/*' => Http::response(['detail' => [['loc' => ['body', 'assessment_scores'], 'msg' => 'At least one assessment competency score is required']]], 422)]);

        try {
            app(\App\Services\AdaptiveRecommendatorClient::class)->initialize(['x' => 1]);
            $this->fail('should have thrown');
        } catch (\RuntimeException) {
        }

        $rec = ServiceFailure::where('service', 'recommender')->first();
        $this->assertSame(422, $rec->status);
        $this->assertStringContainsString('At least one assessment', $rec->body);
    }

    public function test_the_diary_keeps_only_the_last_two_weeks(): void
    {
        $old = ServiceFailure::create(['service' => 'reader', 'status' => 503, 'what' => 'old']);
        \Illuminate\Support\Facades\DB::table('service_failures')->where('id', $old->id)->update(['created_at' => now()->subDays(15)]);

        ServiceFailure::record('reader', null, 'new one', [], 'x');

        $this->assertSame(['new one'], ServiceFailure::pluck('what')->all());
    }

    public function test_the_admin_sees_the_diary_on_the_dashboard_and_nothing_when_it_is_empty(): void
    {
        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Admin']);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertDontSee('Recent service problems');

        ServiceFailure::record('reader', $this->reply(429, '<script>alert(1)</script> Too Many Requests', ['Content-Type' => 'text/html']), 'The reading checker is waking up or busy right now (error 429).', [429, 429, 429, 429]);

        $page = $this->get(route('admin.dashboard'))->assertOk();
        $page->assertSee('Recent service problems')->assertSee('Reading checker')->assertSee('Answered 429')->assertSee('tried 4 times')->assertSee('Too Many Requests');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $page->getContent(), 'what a service sends back is shown as text, never run');
    }
}

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

    public function test_the_admin_sees_the_diary_on_system_health_and_an_honest_empty_note_when_it_is_empty(): void
    {
        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Admin']);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)->get(route('admin.health'))->assertOk()->assertSee('Problems in the last 14 days')->assertSee('Nothing has gone wrong')->assertSee('Check now');

        ServiceFailure::record('reader', $this->reply(429, '<script>alert(1)</script> Too Many Requests', ['Content-Type' => 'text/html']), 'The reading checker is waking up or busy right now (error 429).', [429, 429, 429, 429]);

        $page = $this->get(route('admin.health'))->assertOk();
        $page->assertSee('Problems in the last 14 days')->assertSee('Reading checker')->assertSee('Answered 429')->assertSee('tried 4 times')->assertSee('Too Many Requests');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $page->getContent(), 'what a service sends back is shown as text, never run');
    }

    // ----- the service check (asks the three services from this server)

    private function fakeAllServices(int $analyzeStatus = 422, mixed $analyzeBody = null, array $analyzeHeaders = []): void
    {
        config(['services.adaptive_recommender.url' => 'https://recommender.test', 'services.adaptive_recommender.key' => 'k']);

        Http::fake([
            'api.ipify.org*' => Http::response('203.0.113.7', 200),
            'reader.test/health' => Http::response(['status' => 'ok', 'model_loaded' => true], 200),
            'reader.test/analyze' => Http::response($analyzeBody ?? ['detail' => 'Audio is silent or nearly silent.'], $analyzeStatus, $analyzeHeaders),
            'generator.test/health' => Http::response(['status' => 'ok'], 200),
            'recommender.test/health' => Http::response(['status' => 'ok'], 200),
        ]);
    }

    public function test_the_service_check_says_all_is_fine_when_every_service_answers_normally(): void
    {
        $this->fakeAllServices();

        $result = app(\App\Services\ServiceCheck::class)->runAndRecord();

        $this->assertSame(0, $result['problems']);
        $this->assertStringContainsString('every service answered normally', $result['summary']);
        $text = implode("\n", $result['lines']);
        $this->assertStringContainsString('203.0.113.7', $text, 'the address this server uses to reach the internet is written down');
        $this->assertStringContainsString('QR codes: OK', $text, 'the server proves it can draw a learner QR code');
        $this->assertStringContainsString('POST /analyze (short silent recording): OK (422)', $text, 'a silence rejection means the request got through');

        $row = ServiceFailure::where('service', 'check')->first();
        $this->assertNotNull($row);
        $this->assertStringContainsString('every service answered normally', $row->what);
        $this->assertStringContainsString('Reading checker, GET /health: OK (200)', $row->body);

        // the probe is a real, tiny WAV recording and carries no child data
        Http::assertSent(fn (Request $r) => $r->url() === 'https://reader.test/analyze'
            && $r->hasFile('file', null, 'check.wav')
            && str_starts_with(collect($r->data())->firstWhere('name', 'file')['contents'] ?? '', 'RIFF'));
    }

    public function test_the_service_check_calls_a_429_from_the_hosting_no_answer_yet_not_a_problem(): void
    {
        // Free hosting turns a request from another hosted service away with a plain "Too Many Requests" while the
        // service sleeps. That is not a broken service, so it is not counted as a problem.
        $this->fakeAllServices(429, 'Too Many Requests', ['Content-Type' => 'text/plain; charset=utf-8']);

        $result = app(\App\Services\ServiceCheck::class)->runAndRecord();

        $this->assertSame(0, $result['problems'], 'a sleeping service is not a problem');
        $this->assertSame(2, $result['notices'], 'both recording sizes got no answer yet');
        $this->assertStringContainsString('nothing is broken', $result['summary']);
        $this->assertStringContainsString('POST /analyze (short silent recording): NO ANSWER YET (429)', implode("\n", $result['lines']));
        $this->assertStringContainsString('Too Many Requests', implode("\n", $result['lines']));

        $states = collect($result['checks'])->where('key', 'reader')->pluck('state', 'what')->all();
        $this->assertSame('asleep', $states['POST /analyze (short silent recording)']);
        $this->assertSame('ok', $states['GET /health'], 'the health page still answered');
    }

    public function test_a_429_from_something_that_is_not_a_sleeping_service_is_still_a_problem(): void
    {
        // Gmail's token page answering 429 is a real refusal, not a sleeping service.
        config(['mail.default' => 'gmail-api', 'services.gmail_send.refresh_token' => 'r', 'services.gmail_send.client_id' => 'c', 'services.gmail_send.client_secret' => 's']);
        $this->fakeAllServices();
        Http::fake([
            'api.ipify.org*' => Http::response('203.0.113.7', 200),
            'oauth2.googleapis.com/*' => Http::response(['error' => 'rate_limit'], 429),
            'reader.test/*' => Http::response(['status' => 'ok'], 200),
            'generator.test/*' => Http::response(['status' => 'ok'], 200),
            'recommender.test/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $result = app(\App\Services\ServiceCheck::class)->run();

        $this->assertSame(1, $result['problems']);
        $this->assertSame('problem', collect($result['checks'])->firstWhere('key', 'mail')['state']);
    }

    public function test_the_service_check_survives_a_service_that_does_not_answer_and_one_that_is_not_set_up(): void
    {
        config(['services.adaptive_recommender.url' => null]);
        Http::fake([
            'api.ipify.org*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('no internet'),
            'reader.test/health' => fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: timed out'),
            'reader.test/analyze' => Http::response(['detail' => 'Audio is silent or nearly silent.'], 422),
            'generator.test/health' => Http::response('<html>Bad gateway</html>', 502),
        ]);

        $result = app(\App\Services\ServiceCheck::class)->run();

        $text = implode("\n", $result['lines']);
        $this->assertSame(1, $result['problems'], 'only the 502 is a real problem');
        $this->assertSame(1, $result['notices'], 'a timeout on a service that sleeps is no answer yet');
        $this->assertStringContainsString('POST /analyze (silent recording as long as a real reading): OK (422)', $text);
        $this->assertStringContainsString('unknown (could not ask)', $text);
        $this->assertStringContainsString('NO ANSWER YET (no answer)', $text);
        $this->assertStringContainsString('PROBLEM (502)', $text);
        $this->assertStringContainsString('Adaptive recommender: not configured', $text);
        $this->assertSame('off', collect($result['checks'])->firstWhere('key', 'recommender')['state']);
    }

    public function test_the_service_check_command_writes_the_result_for_the_dashboard(): void
    {
        $this->fakeAllServices();

        $this->artisan('services:check')->assertSuccessful();

        $this->assertSame(1, ServiceFailure::where('service', 'check')->count());
    }

    public function test_only_the_admin_can_press_the_check_button(): void
    {
        $this->fakeAllServices();

        $this->post(route('admin.service-check'))->assertRedirect(route('login'));

        $teacher = User::create(['first_name' => 'Tess', 'last_name' => 'Teacher', 'email' => 'tess@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Teacher']);
        $teacher->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($teacher)->post(route('admin.service-check'))->assertForbidden();
        $this->assertSame(0, ServiceFailure::count());

        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Admin']);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($admin)->post(route('admin.service-check'))->assertRedirect()->assertSessionHas('status');

        // A check that found nothing wrong is not listed as a problem; its result is the newest line on System health.
        $page = $this->get(route('admin.health'))->assertOk();
        $page->assertSee('every service answered normally')->assertSee('Working')->assertSee('Technical details from the newest check');
    }

    public function test_pressing_the_check_button_twice_in_a_minute_only_checks_once(): void
    {
        $this->fakeAllServices();
        $admin = User::create(['first_name' => 'Ada', 'last_name' => 'Admin', 'email' => 'ada@example.com', 'password' => 'Passw0rd!', 'user_type' => 'Admin']);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)->post(route('admin.service-check'))->assertSessionHas('status');
        $second = $this->post(route('admin.service-check'));

        $this->assertStringContainsString('ran a moment ago', session('status'));
        $this->assertSame(1, ServiceFailure::where('service', 'check')->count());
    }

    // ----- a sleeping service is woken by a request that stays connected, then the real request is sent

    /** The requests that went to the reading checker, in order (the side lookup of curriculum codes is not counted). */
    private function readerCalls(): array
    {
        return collect(Http::recorded())
            ->map(fn ($pair) => $pair[0])
            ->filter(fn (Request $r) => str_contains($r->url(), 'reader.test'))
            ->map(fn (Request $r) => $r->method().' '.parse_url($r->url(), PHP_URL_PATH))
            ->values()->all();
    }

    private function wakeUpSettings(): void
    {
        \Illuminate\Support\Facades\Cache::flush();
        config(['services.reading_ai.ready_wait' => 1, 'services.activity_ai.ready_wait' => 1]);
    }

    public function test_a_recording_waits_for_a_checker_that_may_be_asleep_before_it_is_sent(): void
    {
        $this->wakeUpSettings();
        Http::fake(['reader.test/health' => Http::response(['status' => 'ok'], 200), 'reader.test/analyze' => Http::response($this->goodAnswer(), 200)]);

        $outcome = app(ReadingAiClient::class)->analyze(UploadedFile::fake()->create('recording.webm', 20, 'audio/webm'), $this->activity());

        $this->assertFalse($outcome['unclear']);
        $this->assertSame(['GET /health', 'POST /analyze'], $this->readerCalls());
        $this->assertTrue(\App\Support\ServiceWake::isKnownAwake('reader'));
    }

    public function test_a_checker_that_answered_a_moment_ago_is_not_asked_about_its_health_again(): void
    {
        $this->wakeUpSettings();
        \App\Support\ServiceWake::markAwake('reader');
        Http::fake(['reader.test/analyze' => Http::response($this->goodAnswer(), 200)]);

        app(ReadingAiClient::class)->analyze(UploadedFile::fake()->create('recording.webm', 20, 'audio/webm'), $this->activity());

        $this->assertSame(0, $this->sentTo('reader.test/health'));
        $this->assertSame(1, $this->sentTo('reader.test/analyze'));
    }

    public function test_a_refused_recording_wakes_the_checker_properly_and_is_sent_again_without_losing_the_child_reading(): void
    {
        $this->wakeUpSettings();
        \App\Support\ServiceWake::markAwake('reader'); // believed awake, but it went back to sleep
        Http::fake([
            'reader.test/health' => Http::response(['status' => 'ok'], 200),
            'reader.test/analyze' => Http::sequence()->push('Too Many Requests', 429, ['Content-Type' => 'text/plain'])->push($this->goodAnswer(), 200),
        ]);

        $outcome = app(ReadingAiClient::class)->analyze(UploadedFile::fake()->create('recording.webm', 20, 'audio/webm'), $this->activity());

        $this->assertFalse($outcome['unclear']);
        $this->assertSame(2, $this->sentTo('reader.test/analyze'));
        $this->assertSame(1, $this->sentTo('reader.test/health'), 'after the refusal the health page is asked and waited for');
        $this->assertSame(0, ServiceFailure::count(), 'a refusal that the wake-up fixed is not a problem worth the team attention');
        $this->assertSame(['POST /analyze', 'GET /health', 'POST /analyze'], $this->readerCalls());
    }

    public function test_a_checker_that_does_not_wake_in_time_does_not_stop_the_recording_being_sent(): void
    {
        $this->wakeUpSettings();
        Http::fake(['reader.test/health' => Http::response('<html>starting</html>', 503), 'reader.test/analyze' => Http::response($this->goodAnswer(), 200)]);

        $outcome = app(ReadingAiClient::class)->analyze(UploadedFile::fake()->create('recording.webm', 20, 'audio/webm'), $this->activity());

        $this->assertFalse($outcome['unclear']);
        $this->assertSame(1, $this->sentTo('reader.test/analyze'));
    }

    public function test_the_generator_is_woken_in_the_background_once_in_a_while_when_the_generate_window_opens(): void
    {
        \Illuminate\Support\Facades\Cache::flush();
        config(['queue.default' => 'database']);
        \Illuminate\Support\Facades\Queue::fake();
        Http::fake();

        $client = app(ActivityAiClient::class);
        $client->wake();
        $client->wake();

        Http::assertNothingSent();
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\WakeServiceJob::class, 1);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\WakeServiceJob::class, fn ($j) => $j->service === 'generator');
    }

    // ----- email: a failed send is visible to the team, and the check says whether the app can still sign in to Gmail

    private function gmailSettings(): void
    {
        config(['mail.default' => 'gmail-api', 'services.gmail_send' => ['client_id' => 'cid', 'client_secret' => 'csecret', 'refresh_token' => 'rtoken']]);
    }

    public function test_an_email_that_could_not_be_sent_is_written_to_the_diary_without_the_recipient(): void
    {
        $this->gmailSettings();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400)]);

        try {
            \Illuminate\Support\Facades\Mail::mailer('gmail-api')->raw('Hello', fn ($m) => $m->to('parent.private@example.com')->subject('Verify'));
            $this->fail('the send should have failed');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Could not refresh the Gmail API access token', $e->getMessage());
        }

        $row = ServiceFailure::where('service', 'mail')->first();
        $this->assertNotNull($row);
        $this->assertSame(400, $row->status);
        $this->assertStringContainsString('could not sign in to Gmail', $row->what);
        $this->assertStringContainsString('invalid_grant', $row->body);
        $this->assertStringNotContainsString('parent.private@example.com', $row->what.$row->body, 'who was being emailed is never written down');
    }

    public function test_the_service_check_says_when_the_app_can_sign_in_to_gmail_and_never_repeats_the_token(): void
    {
        $this->gmailSettings();
        $this->fakeAllServices();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['access_token' => 'SECRET-ACCESS-TOKEN', 'expires_in' => 3599, 'scope' => 'gmail.send'], 200)]);

        $result = app(\App\Services\ServiceCheck::class)->runAndRecord();

        $text = implode("\n", $result['lines']);
        $this->assertStringContainsString('Email (Gmail), sign in with the stored token: OK (200)', $text);
        $this->assertSame(0, $result['problems']);
        $this->assertStringNotContainsString('SECRET-ACCESS-TOKEN', $text.json_encode(ServiceFailure::all()->toArray()), 'the token is never written anywhere');
    }

    public function test_the_service_check_reports_an_expired_gmail_token_and_a_missing_one(): void
    {
        $this->gmailSettings();
        $this->fakeAllServices();
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Token has been expired or revoked.'], 400)]);

        $expired = app(\App\Services\ServiceCheck::class)->run();
        $this->assertSame(1, $expired['problems']);
        $this->assertStringContainsString('PROBLEM (400)', implode("\n", $expired['lines']));
        $this->assertStringContainsString('invalid_grant', implode("\n", $expired['lines']));

        config(['services.gmail_send.refresh_token' => null]);
        $missing = app(\App\Services\ServiceCheck::class)->run();
        $this->assertSame(1, $missing['problems']);
        $this->assertStringContainsString('GMAIL_SEND_REFRESH_TOKEN is empty', implode("\n", $missing['lines']));
    }
}

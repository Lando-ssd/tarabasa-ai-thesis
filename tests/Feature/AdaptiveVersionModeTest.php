<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Services\AdaptiveLearningService;
use App\Services\AdaptiveRecommendatorClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The team redeployed an OLDER recommender (1.0.0, three grouped competencies) than the one this app was built for
 * (2.0.0, MATATAG subdomains) without notice, and every call was refused, so no child's learning path ever filled. The
 * app now asks the service which version it speaks and uses the matching contract, translating version 1's answers into
 * the subdomains every screen reads. These pin both modes down.
 */
class AdaptiveVersionModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['services.adaptive_recommender.url' => 'https://rec.test', 'services.adaptive_recommender.key' => 'k', 'services.adaptive_recommender.api' => 'auto']);
    }

    private function kid(array $o = []): Learner
    {
        return Learner::create($o + ['learner_code' => 'TB26-222220', 'first_name' => 'Maria', 'last_name' => 'Cruz', 'grade_level' => 'Grade 1', 'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => 'Beginning', 'reading_rung' => 1]);
    }

    private function activity(string $competency = 'foundational_reading', string $tier = 'Easy'): Activity
    {
        return Activity::create([
            'created_by_teacher_id' => null, 'grade_level' => 'Grade 1', 'competency' => $competency, 'competency_label' => 'x', 'activity_type' => 'word_reading',
            'difficulty_tier' => $tier, 'title' => 'Words', 'instructions' => 'Read', 'passage_text' => 'cat dog pig hen cow bat', 'word_count' => 6, 'status' => 'Approved',
        ]);
    }

    private function reading(Learner $kid, Activity $act): ReadingSession
    {
        return ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $act->id, 'session_type' => 'Practice', 'initiated_by' => 'Teacher', 'accuracy_percent' => 83, 'wcpm' => 40, 'level_before' => 'Beginning', 'level_after' => 'Beginning', 'timestamp' => now()]);
    }

    private function blank(): array
    {
        return ['proficiency' => null, 'difficulty' => null, 'confidence' => 0, 'attempt_count' => 0];
    }

    private function fakeVersion1(): void
    {
        $blank = $this->blank();
        $states = ['foundational_reading' => ['proficiency' => 39, 'difficulty' => 'easy', 'confidence' => 0.6, 'attempt_count' => 0], 'reading_fluency' => $blank, 'reading_comprehension' => $blank];
        Http::fake([
            'rec.test/health' => Http::response(['status' => 'ok', 'version' => '1.0.0'], 200),
            'rec.test/initialize' => Http::response(['student_id' => 1, 'grade' => 1, 'competency_states' => $states, 'next_recommendation' => ['competency' => 'foundational_reading', 'difficulty' => 'easy', 'reason_codes' => ['x']]], 200),
            'rec.test/recommend' => Http::response([
                'student_id' => 1, 'grade' => 1,
                'completed_competency_update' => ['competency' => 'foundational_reading', 'attempt_score' => 78.64, 'updated_proficiency' => 50.89],
                'updated_state' => ['foundational_reading' => ['proficiency' => 50.89, 'difficulty' => 'easy', 'confidence' => 0.68, 'attempt_count' => 1]] + $states,
                'next_recommendation' => ['competency' => 'foundational_reading', 'difficulty' => 'easy', 'reason_codes' => ['x']],
            ], 200),
        ]);
    }

    public function test_the_app_asks_the_service_which_version_it_speaks_and_remembers_it(): void
    {
        Http::fake(['rec.test/health' => Http::response(['status' => 'ok', 'version' => '1.0.0'], 200)]);

        $client = app(AdaptiveRecommendatorClient::class);
        $this->assertSame(1, $client->apiMajor());
        $this->assertSame(1, $client->apiMajor());
        Http::assertSentCount(1); // asked once, then remembered

        Cache::flush();
        Http::fake(['rec2.test/health' => Http::response(['version' => '2.0.0'], 200)]);
        config(['services.adaptive_recommender.url' => 'https://rec2.test']);
        $this->assertSame(2, $client->apiMajor());
    }

    public function test_an_unknown_version_is_not_guessed_and_a_setting_can_force_one(): void
    {
        Http::fake(['rec.test/health' => Http::response('Too Many Requests', 429)]);
        $this->assertNull(app(AdaptiveRecommendatorClient::class)->apiMajor(), 'a sleeping service tells us nothing, so nothing is remembered');
        $this->assertNull(Cache::get('recommender-api-major'));

        config(['services.adaptive_recommender.api' => '1']);
        $this->assertSame(1, app(AdaptiveRecommendatorClient::class)->apiMajor());
    }

    public function test_with_version_1_the_first_check_starts_the_three_competency_state_and_the_app_translates_it_to_subdomains(): void
    {
        $this->fakeVersion1();
        $kid = $this->kid();

        $this->assertTrue(app(AdaptiveLearningService::class)->initializeFromDiagnostic($kid, 39.0, 'phonics_easy'));

        // what was sent: version 1's own shape (competencies), never the subdomain keys that version 2 uses
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/initialize')
            && $r['assessment_scores'] === ['foundational_reading' => 39.0] && $r['last_competency'] === 'foundational_reading' && $r['grade'] === 1
            && ! isset($r['last_subdomain']));

        $kid->refresh();
        $this->assertSame(39, $kid->competency_states['foundational_reading']['proficiency']);
        $this->assertSame(39, $kid->subdomain_states['Phonics and Word Study']['proficiency']);
        $this->assertNull($kid->subdomain_states['Comprehending and Analyzing Text']['proficiency'], 'what was not measured stays unassessed');
        $this->assertSame('foundational_reading', $kid->next_recommended_competency);
        $this->assertSame('Phonics and Word Study', $kid->next_recommended_subdomain);
        $this->assertSame('easy', $kid->next_recommended_difficulty);
        $this->assertSame('Phonics and Word Study', $kid->focusSubdomain(), 'the child road and the picked-for-you label read this');
    }

    public function test_with_version_1_a_practice_reading_sends_the_three_scores_and_stores_the_new_state(): void
    {
        $this->fakeVersion1();
        $kid = $this->kid();
        $service = app(AdaptiveLearningService::class);
        $service->initializeFromDiagnostic($kid, 39.0, 'phonics_easy');
        $act = $this->activity();
        $session = $this->reading($kid, $act);
        $result = ['accuracy' => ['accuracy_score' => 83.3], 'speed' => ['speed_score' => 60.0], 'prosody' => ['prosody_score' => 55.0]];

        $service->recordAttempt($kid->fresh(), $act, $session, $result);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/recommend')
            && $r['completed_activity']['competency'] === 'foundational_reading' && $r['completed_activity']['difficulty'] === 'easy'
            && $r['performance'] === ['accuracy_score' => 83.3, 'speed_score' => 60.0, 'prosody_score' => 55.0]
            && array_keys($r['current_state']) === ['foundational_reading', 'reading_fluency', 'reading_comprehension']
            && $r['current_state']['foundational_reading']['proficiency'] == 39);

        $this->assertEquals(78.64, $session->fresh()->adaptive_attempt_score);
        $this->assertSame('Phonics and Word Study', $session->fresh()->adaptive_subdomain);
        $kid->refresh();
        $this->assertEquals(50.89, $kid->subdomain_states['Phonics and Word Study']['proficiency']);
        $this->assertSame(1, $kid->competency_states['foundational_reading']['attempt_count']);
    }

    public function test_with_version_1_a_reading_without_the_scores_the_service_needs_is_not_sent(): void
    {
        $this->fakeVersion1();
        $kid = $this->kid(['competency_states' => ['foundational_reading' => ['proficiency' => 39, 'difficulty' => 'easy', 'confidence' => 0.6, 'attempt_count' => 0]]]);
        $fluency = $this->activity('reading_fluency', 'Medium');
        $comprehension = $this->activity('reading_comprehension', 'Medium');
        $service = app(AdaptiveLearningService::class);

        // fluency needs accuracy, speed AND prosody; comprehension needs the quiz score
        $service->recordAttempt($kid, $fluency, $this->reading($kid, $fluency), ['accuracy' => ['accuracy_score' => 90], 'speed' => ['speed_score' => 70]]);
        $service->recordAttempt($kid, $comprehension, $this->reading($kid, $comprehension), ['accuracy' => ['accuracy_score' => 90], 'speed' => ['speed_score' => 70], 'prosody' => ['prosody_score' => 60]]);

        Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/recommend'));
    }

    public function test_with_version_1_a_comprehension_reading_counts_under_comprehending_and_analyzing_text(): void
    {
        $this->fakeVersion1();
        $kid = $this->kid(['competency_states' => ['foundational_reading' => ['proficiency' => 39, 'difficulty' => 'easy', 'confidence' => 0.6, 'attempt_count' => 0], 'reading_fluency' => $this->blank(), 'reading_comprehension' => $this->blank()]]);
        $act = $this->activity('reading_comprehension', 'Medium');
        $session = $this->reading($kid, $act);

        app(AdaptiveLearningService::class)->recordAttempt($kid, $act, $session, ['accuracy' => ['accuracy_score' => 90]], 66.67);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/recommend') && $r['completed_activity']['competency'] === 'reading_comprehension' && $r['performance'] === ['accuracy_score' => 90, 'comprehension_score' => 66.67]);
        $this->assertSame('Comprehending and Analyzing Text', $session->fresh()->adaptive_subdomain);
    }

    public function test_with_version_2_nothing_changes_and_the_subdomain_contract_is_still_used(): void
    {
        Http::fake([
            'rec.test/health' => Http::response(['version' => '2.0.0'], 200),
            'rec.test/initialize' => Http::response(['student_id' => 1, 'grade' => 1, 'subdomain_states' => ['Phonics and Word Study' => ['proficiency' => 39, 'difficulty' => 'easy', 'confidence' => 0.6, 'attempt_count' => 0]], 'next_recommendation' => ['subdomain' => 'Phonics and Word Study', 'difficulty' => 'easy']], 200),
        ]);
        $kid = $this->kid();

        app(AdaptiveLearningService::class)->initializeFromDiagnostic($kid, 39.0, 'phonics_easy');

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/initialize') && $r['assessment_scores'] === ['Phonics and Word Study' => 39.0] && $r['last_subdomain'] === 'Phonics and Word Study' && ! isset($r['last_competency']));
        $this->assertSame('Phonics and Word Study', $kid->fresh()->next_recommended_subdomain);
        $this->assertNull($kid->fresh()->competency_states, 'version 2 never touches the version 1 columns');
    }
}

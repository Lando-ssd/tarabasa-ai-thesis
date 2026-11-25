<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Services\LearnerDiagnosticService;
use App\Support\LearnerCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Found in the pre-defense run (25 November 2026): asking for a first-check passage AFTER the check was finished started a
 * brand new check (the Android back button, or a stale screen). That locked the child out of every other screen until she read
 * it again, and the new result could place her somewhere else. A finished child is now told it is done (phone) or sent home
 * (website). A check still in progress carries on, and a child who has not started still starts.
 */
class FirstCheckNotRestartedTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

    private function learner(): Learner
    {
        $n = ++$this->seq;

        return Learner::create([
            'learner_code' => LearnerCode::generate(), 'first_name' => "Kid{$n}", 'last_name' => 'Cruz', 'grade_level' => 'Grade 1',
            'pin' => '1234', 'avatar_id' => 'A', 'mastery_level' => null, 'reading_stage' => 'sentences',
            'placement_answers' => ['q1' => 'yes', 'q2' => 'yes', 'q3' => 'yes'],
        ]);
    }

    /** A child whose first check is finished: a saved Diagnostic reading and no check in progress. */
    private function finishedChild(): Learner
    {
        $kid = $this->learner();
        $kid->update(['mastery_level' => 'Beginning', 'reading_rung' => 1]);
        $activity = Activity::create([
            'grade_level' => 'Grade 1', 'competency' => 'foundational_reading', 'competency_label' => 'Foundational Reading',
            'activity_type' => 'word_reading', 'difficulty_tier' => 'Easy', 'title' => 'Short Words', 'instructions' => 'Read.',
            'passage_text' => 'cat map six leg fan log', 'reference_text' => 'cat map six leg fan log', 'word_count' => 6, 'status' => 'Approved',
        ]);
        ReadingSession::create(['learner_id' => $kid->id, 'activity_id' => $activity->id, 'session_type' => 'Diagnostic', 'initiated_by' => 'Teacher', 'accuracy_percent' => 80, 'timestamp' => now()]);

        return $kid;
    }

    public function test_a_finished_child_asking_for_a_passage_on_the_phone_is_told_it_is_done_and_no_new_check_starts(): void
    {
        $kid = $this->finishedChild();
        $token = $kid->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/learner/diagnostic/passage')
            ->assertStatus(409)
            ->assertJsonPath('error', 'diagnostic_done');

        $service = app(LearnerDiagnosticService::class);
        $this->assertNull($service->diagnosticState($kid->fresh()), 'No new check may be opened');
        $this->assertTrue($service->hasGenuinelyCompletedDiagnostic($kid->fresh()));

        // ... so the rest of the app is still open to her (this used to answer 403 diagnostic_required afterwards).
        $this->withToken($token)->getJson('/api/learner/dashboard')->assertOk();
    }

    public function test_a_finished_child_asking_for_a_passage_on_the_website_is_sent_home_and_no_new_check_starts(): void
    {
        $kid = $this->finishedChild();

        $this->actingAs($kid, 'learner')->get(route('learner.diagnostic.passage'))->assertRedirect(route('learner.dashboard'));

        $this->assertNull(app(LearnerDiagnosticService::class)->diagnosticState($kid->fresh()));
        $this->actingAs($kid->fresh(), 'learner')->get(route('learner.dashboard'))->assertOk();
    }

    public function test_a_child_who_has_not_started_still_starts_the_check_and_one_in_progress_carries_on(): void
    {
        $kid = $this->learner();
        $token = $kid->createToken('test')->plainTextToken;

        $first = $this->withToken($token)->getJson('/api/learner/diagnostic/passage')->assertOk()->assertJsonPath('passageNumber', 1)->json('activity.id');
        $this->assertNotNull(app(LearnerDiagnosticService::class)->diagnosticState($kid->fresh()));

        // Asking again mid-check returns the same passage, not a refusal.
        $this->withToken($token)->getJson('/api/learner/diagnostic/passage')->assertOk()->assertJsonPath('activity.id', $first);
        $this->actingAs($kid->fresh(), 'learner')->get(route('learner.diagnostic.passage'))->assertOk();
    }
}

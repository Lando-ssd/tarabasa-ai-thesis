<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\ReadingSession;
use App\Support\DiagnosticPlacement;
use Illuminate\Support\Facades\Log;

/**
 * Everything the app does with the Adaptive_Recommendator v2, in one place
 * so the diagnostic and the practice reading can't drift into two copies.
 *
 * The recommender is stateless: this app holds each learner's per-subdomain
 * state (proficiency, difficulty, confidence, attempts), sends it with every
 * call, and stores what comes back. Every failure is caught, logged and
 * swallowed: a learner must never be blocked from reading, from finishing the
 * assessment or from picking an activity because this enhancement is down.
 *
 * What the app can honestly send is decided by the recommender's own scoring
 * rules (its engine.py), not by us: oral word accuracy counts under Phonics and
 * Word Study, answered comprehension items under Comprehending and Analyzing
 * Text, and an attempt without that evidence is not sent at all rather than
 * padded with a made-up score.
 */
class AdaptiveLearningService
{
    private const COMPREHENSION_SUBDOMAIN = 'Comprehending and Analyzing Text';

    // The recommender rejects any other key with a 422 (its models are strict).
    private const MEASUREMENT_KEYS = [
        'word_accuracy_percent',
        'wcpm',
        'experimental_prosody_indicator',
        'comprehension_score',
    ];

    private const HISTORY_LIMIT = 20;

    public function __construct(
        private AdaptiveRecommendatorClient $client,
        private MatatagAlignmentResolver $alignment,
    ) {}

    /**
     * Called once when the first-login diagnostic finishes. The diagnostic is
     * an oral reading check, so its result is the starting estimate for the
     * subdomain that oral accuracy counts under. $accuracy is that result as a
     * placement score (DiagnosticPlacement::score), in the recommender's own
     * scale, not the raw percentage of one item. Every other subdomain starts
     * unassessed, exactly as the recommender's own README specifies.
     */
    public function initializeFromDiagnostic(Learner $learner, float $accuracy): bool
    {
        $grade = $this->gradeOf($learner);
        $subdomain = config('matatag_subdomains.diagnostic_subdomain');

        if (! in_array($subdomain, $this->alignment->gradeSubdomains($grade), true)) {
            return false;
        }

        try {
            $response = $this->client->initialize([
                'student_id' => $learner->id,
                'grade' => $grade,
                'assessment_scores' => [$subdomain => round($accuracy, 2)],
                'last_subdomain' => $subdomain,
            ]);
        } catch (\RuntimeException $e) {
            Log::warning('Adaptive Recommendator initialize() failed, continuing without a recommendation', ['error' => $e->getMessage()]);

            return false;
        }

        $this->store($learner, $response['subdomain_states'] ?? null, $response['next_recommendation'] ?? []);

        return true;
    }

    /**
     * Called after every real scored Practice reading, with the raw Reading-api
     * result. Sends the recommender the measurements Reading-api itself
     * produced, without converting raw WCPM into an invented percentage.
     */
    public function recordAttempt(Learner $learner, Activity $activity, ReadingSession $session, array $result, ?float $comprehensionScore = null): void
    {
        $subdomain = $this->alignment->subdomainFor($activity);

        if ($subdomain === null || ! $activity->difficulty_tier) {
            return;
        }

        $measurements = collect($result['measurements'] ?? [])
            ->only(self::MEASUREMENT_KEYS)
            ->filter(fn ($value) => $value !== null)
            ->all();

        if ($comprehensionScore !== null) {
            $measurements['comprehension_score'] = $comprehensionScore;
        }

        // The recommender scores each subdomain from specific evidence and
        // answers 422 without it, so don't send an attempt that lacks it.
        $needed = $subdomain === self::COMPREHENSION_SUBDOMAIN ? 'comprehension_score' : 'word_accuracy_percent';
        if (! isset($measurements[$needed])) {
            return;
        }

        if ($learner->subdomain_states === null && ! $this->initializeFromLatestDiagnostic($learner)) {
            return;
        }

        $grade = $this->gradeOf($learner);
        $context = $this->alignment->contextFor($activity);

        try {
            $response = $this->client->recommend([
                'student_id' => $learner->id,
                'grade' => $grade,
                'completed_activity' => [
                    'activity_id' => (string) $activity->id,
                    'bundle_id' => $activity->generation_id,
                    'subdomain' => $subdomain,
                    'competency_code' => $context['competency_code'],
                    'activity_type' => $activity->activity_type,
                    'difficulty' => strtolower($activity->difficulty_tier),
                ],
                'performance' => [
                    'measurement_profile' => $result['measurement_profile'] ?? 'oral_passage_fluency',
                    'measurements' => $measurements,
                    'quality_flags' => array_values($result['quality_flags'] ?? []),
                    'scoring_profile_version' => $result['scoring_profile_version'] ?? null,
                ],
                'current_state' => $this->stateForGrade($learner->subdomain_states, $grade),
                'recent_history' => $this->recentHistory($learner, $grade),
            ]);
        } catch (\RuntimeException $e) {
            Log::warning('Adaptive Recommendator call failed, continuing without a recommendation', ['error' => $e->getMessage()]);

            return;
        }

        $update = $response['completed_subdomain_update'] ?? [];

        // An attempt the recommender rejected (for example an audio quality
        // flag) is not evidence, so it must not count toward future history.
        if (($update['attempt_accepted'] ?? false) === true) {
            $session->update([
                'adaptive_attempt_score' => $update['attempt_score'] ?? null,
                'adaptive_subdomain' => $subdomain,
            ]);
        }

        $this->store($learner, $response['updated_state'] ?? null, $response['next_recommendation'] ?? []);
    }

    /**
     * A learner who finished their diagnostic before this integration worked
     * (or while the recommender was down) has no state yet. Start it from
     * their most recent diagnostic accuracy instead of leaving them without
     * adaptive guidance forever.
     */
    private function initializeFromLatestDiagnostic(Learner $learner): bool
    {
        $diagnostic = ReadingSession::where('learner_id', $learner->id)
            ->where('session_type', 'Diagnostic')
            ->whereNotNull('accuracy_percent')
            ->orderByDesc('timestamp')
            ->orderByDesc('id')
            ->first();

        if ($diagnostic === null) {
            return false;
        }

        // The same scale the diagnostic itself uses when it finishes: the rung
        // the child was last tested on and how well they did there.
        $rung = $diagnostic->activity ? DiagnosticPlacement::rungOf($diagnostic->activity) : 'medium';
        $score = DiagnosticPlacement::score($rung, (float) $diagnostic->accuracy_percent);

        if (! $this->initializeFromDiagnostic($learner, $score)) {
            return false;
        }

        $learner->refresh();

        return $learner->subdomain_states !== null;
    }

    /**
     * The recommender requires exactly the subdomains valid for the learner's
     * grade. A learner promoted between grades carries the old grade's set
     * (Book and Print Knowledge only exists in Grade 1; Phonological
     * Awareness is gone by Grade 3), so drop what no longer applies and add
     * what is new as unassessed. Nothing is invented for the new ones.
     */
    private function stateForGrade(array $states, int $grade): array
    {
        $blank = ['proficiency' => null, 'difficulty' => null, 'confidence' => 0, 'attempt_count' => 0];

        return collect($this->alignment->gradeSubdomains($grade))
            ->mapWithKeys(fn (string $name) => [$name => $states[$name] ?? $blank])
            ->all();
    }

    private function recentHistory(Learner $learner, int $grade): array
    {
        $valid = $this->alignment->gradeSubdomains($grade);

        return ReadingSession::where('learner_id', $learner->id)
            ->whereNotNull('adaptive_subdomain')
            ->whereNotNull('adaptive_attempt_score')
            ->with('activity')
            ->orderBy('timestamp')
            ->orderBy('id')
            ->get()
            ->filter(fn (ReadingSession $s) => $s->activity?->difficulty_tier && in_array($s->adaptive_subdomain, $valid, true))
            ->map(fn (ReadingSession $s) => [
                'subdomain' => $s->adaptive_subdomain,
                'difficulty' => strtolower($s->activity->difficulty_tier),
                'score' => (float) $s->adaptive_attempt_score,
                'activity_id' => $s->activity_id,
                'accepted' => true,
            ])
            ->take(-self::HISTORY_LIMIT)
            ->values()
            ->all();
    }

    private function store(Learner $learner, ?array $states, array $recommendation): void
    {
        $learner->update([
            'subdomain_states' => $states,
            'next_recommended_subdomain' => $recommendation['subdomain'] ?? null,
            'next_recommended_difficulty' => $recommendation['difficulty'] ?? null,
        ]);
    }

    private function gradeOf(Learner $learner): int
    {
        return (int) substr((string) $learner->grade_level, 6);
    }
}

<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\ReadingSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The first-login diagnostic staircase — extracted out of
 * LearnerDiagnosticController (previously `private` methods returning
 * Blade Views) for the same reason as LearnerReadingService: one real
 * implementation, called by both the web controller and the mobile API
 * controller. No behavior change from the original — see CLAUDE.md's
 * "Mobile API layer" entry.
 *
 * The staircase's in-progress state (which tier/variant is current, how
 * many passages done, accuracy history) and the "3 unclear attempts"
 * counter both used to live in the web session — moved to the database
 * cache store, keyed by learner_id, so a token-authenticated mobile
 * request (which carries no session cookie) sees the exact same state a
 * web request would.
 */
class LearnerDiagnosticService
{
    private const TIERS = ['easy', 'medium', 'hard'];

    private const TIER_TO_MASTERY = [
        'easy' => 'Beginning',
        'medium' => 'Developing',
        'hard' => 'Proficient',
    ];

    private const MASTERY_TO_RESULT_LABEL = [
        'Beginning' => '🌱 You\'re a Rising Reader!',
        'Developing' => '🌿 You\'re a Growing Reader!',
        'Proficient' => '🌟 You\'re a Super Reader!',
    ];

    private const MAX_PASSAGES = 3;

    public const MAX_UNCLEAR_ATTEMPTS = 3;

    private const STATE_TTL_HOURS = 6;

    public function ensureBundleGenerated(Learner $learner): array
    {
        $existing = $this->diagnosticState($learner);
        if ($existing !== null) {
            return $existing;
        }

        $competencies = config('activity_competencies.competencies');
        $gradeNumber = (int) substr($learner->grade_level, 6);

        try {
            $data = app(ActivityAiClient::class)->generateBundle([
                'grade' => $gradeNumber,
                'competency' => 'reading_fluency',
                'activity_type' => 'passage_reading',
                'variants_per_level' => 2,
                'topic' => null,
            ]);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages(['diagnostic' => $e->getMessage()]);
        }

        $activities = Activity::createManyFromBundle($data, [
            'created_by_teacher_id' => null,
            'grade_level' => $learner->grade_level,
            'competency' => 'reading_fluency',
            'competency_label' => $data['competency_label'] ?? $competencies['reading_fluency']['label'],
            'activity_type' => 'passage_reading',
            'purpose' => 'diagnostic',
        ]);

        $activityIds = ['easy' => [], 'medium' => [], 'hard' => []];
        foreach ($activities as $activity) {
            $activityIds[strtolower($activity->difficulty_tier)][] = $activity->id;
        }

        $state = [
            'activity_ids' => $activityIds,
            'variant_used' => ['easy' => 0, 'medium' => 0, 'hard' => 0],
            'current_tier' => 'medium',
            'passages_done' => 0,
            'level_before' => $learner->mastery_level,
            'accuracy_history' => [],
        ];

        $this->putState($learner, $state);

        return $state;
    }

    public function currentActivityId(array $state): int
    {
        $tier = $state['current_tier'];
        $variantIndex = $state['variant_used'][$tier];

        return $state['activity_ids'][$tier][$variantIndex];
    }

    public function diagnosticState(Learner $learner): ?array
    {
        return Cache::get($this->stateCacheKey($learner));
    }

    /**
     * A real, pre-existing bug found while building the mobile API (not
     * introduced by that work — the same flawed check already lived,
     * duplicated, in LearnerAuthController::login() and the web
     * EnsureDiagnosticComplete middleware before this extraction; this is
     * simply the first time all three call sites shared one
     * implementation to fix at once). The old check was "does any real
     * Diagnostic ReadingSession exist" — true after passage 1 of up to 3,
     * since applyStaircaseStep() persists a session for every attempted
     * passage immediately, not just the final one. A Learner who reached
     * the dashboard directly (bookmark, back button, app restart) between
     * passage 1 and the staircase's real conclusion would be waved
     * through with mastery_level still whatever the placement-quiz guess
     * was (or null), never finishing their real diagnostic. The correct
     * signal combines both real facts: at least one Diagnostic session
     * exists AND the staircase has no in-progress cached state left (that
     * cache is only ever cleared by finishDiagnostic()).
     */
    public function hasGenuinelyCompletedDiagnostic(Learner $learner): bool
    {
        if ($this->diagnosticState($learner) !== null) {
            return false;
        }

        return ReadingSession::where('learner_id', $learner->id)
            ->where('session_type', 'Diagnostic')
            ->exists();
    }

    /**
     * Orchestrates one real diagnostic attempt: scores it via Reading-api,
     * then either records an unclear attempt or advances the staircase.
     * $state must already exist (fetched by the caller via
     * diagnosticState()) — this deliberately never auto-creates a bundle,
     * matching the original behavior of only generating one from show()/
     * passage(), never from a raw submit.
     */
    public function recordAttempt(Learner $learner, array $state, Activity $activity, UploadedFile $audio, ReadingAiClient $readingAi): array
    {
        $outcome = $readingAi->analyze($audio, $activity->reference_text ?? $activity->passage_text);

        if ($outcome['unclear']) {
            $attempts = $this->incrementUnclearAttempts($learner);

            if ($attempts >= self::MAX_UNCLEAR_ATTEMPTS) {
                $this->clearUnclearAttempts($learner);
                $this->clearState($learner);

                return ['status' => 'unclear', 'final' => true];
            }

            return ['status' => 'unclear', 'final' => false];
        }

        $this->clearUnclearAttempts($learner);

        return $this->applyStaircaseStep($learner, $state, $activity, $outcome['result']);
    }

    /**
     * >=90% moves up a tier (stop if already Hard), <70% moves down a tier
     * (stop if already Easy), 70-89% stops right here regardless of
     * passage count. Capped at 3 passages regardless of outcome.
     */
    private function applyStaircaseStep(Learner $learner, array $state, Activity $activity, array $result): array
    {
        $accuracy = (float) ($result['accuracy']['accuracy_score'] ?? 0);
        $tier = $state['current_tier'];

        ReadingSession::create([
            'learner_id' => $learner->id,
            'activity_id' => $activity->id,
            'accuracy_percent' => $accuracy,
            'wcpm' => $result['speed']['wcpm'] ?? null,
            'speed_score' => $result['speed']['speed_score'] ?? null,
            'prosody_score' => $result['prosody']['prosody_score'] ?? null,
            'pronunciation_score' => null,
            'fluency_score' => null,
            'mispronunciation_count' => null,
            'skipped_word_count' => $result['accuracy']['deletions'] ?? null,
            'substitution_count' => $result['accuracy']['substitutions'] ?? null,
            'repetition_count' => null,
            'insertion_count' => $result['accuracy']['insertions'] ?? null,
            'word_feedback' => $result['accuracy']['word_feedback'] ?? null,
            'level_before' => $state['level_before'],
            'level_after' => self::TIER_TO_MASTERY[$tier],
            'flagged_needs_attention' => $accuracy < 70,
            'session_type' => 'Diagnostic',
            'initiated_by' => 'Parent',
        ]);

        $state['passages_done']++;
        $state['accuracy_history'][] = ['tier' => $tier, 'accuracy' => $accuracy];

        $tierIndex = array_search($tier, self::TIERS, true);
        $nextTier = null;

        if ($accuracy >= 90) {
            $nextTier = $tierIndex < count(self::TIERS) - 1 ? self::TIERS[$tierIndex + 1] : null;
        } elseif ($accuracy < 70) {
            $nextTier = $tierIndex > 0 ? self::TIERS[$tierIndex - 1] : null;
        }

        $reachedCap = $state['passages_done'] >= self::MAX_PASSAGES;

        if ($nextTier === null || $reachedCap) {
            return $this->finishDiagnostic($learner, $tier, $accuracy);
        }

        $state['variant_used'][$nextTier] = min($state['variant_used'][$nextTier] + 1, 1);
        $state['current_tier'] = $nextTier;

        $this->putState($learner, $state);

        return [
            'status' => 'continue',
            'message' => $state['passages_done'] >= self::MAX_PASSAGES - 1
                ? "One more short one, then we're done!"
                : "Let's see how the next one goes!",
            'passagesDone' => $state['passages_done'],
            'maxPassages' => self::MAX_PASSAGES,
        ];
    }

    private function finishDiagnostic(Learner $learner, string $landedTier, float $lastAccuracy): array
    {
        $finalLevel = self::TIER_TO_MASTERY[$landedTier];

        $learner->update(['mastery_level' => $finalLevel]);

        Notification::notifyForLearner(
            $learner,
            Notification::TYPE_LEVEL_CONFIRMED,
            "{$learner->first_name}'s starting reading level has been confirmed: {$finalLevel}.",
            includeTeacher: false
        );

        $this->initializeAdaptiveRecommendation($learner, $lastAccuracy);

        $this->clearState($learner);

        return [
            'status' => 'finished',
            'learner' => $learner->fresh(),
            'finalLevel' => $finalLevel,
            'resultLabel' => self::MASTERY_TO_RESULT_LABEL[$finalLevel],
        ];
    }

    private function initializeAdaptiveRecommendation(Learner $learner, float $lastAccuracy): void
    {
        try {
            $response = app(AdaptiveRecommendatorClient::class)->initialize([
                'student_id' => $learner->id,
                'grade' => (int) substr($learner->grade_level, 6),
                'assessment_scores' => [
                    'reading_fluency' => $lastAccuracy,
                ],
                'last_competency' => 'reading_fluency',
            ]);
        } catch (\RuntimeException $e) {
            Log::warning('Adaptive Recommendator initialize() failed, continuing without a recommendation', ['error' => $e->getMessage()]);

            return;
        }

        $learner->update([
            'competency_states' => $response['competency_states'],
            'next_recommended_competency' => $response['next_recommendation']['competency'] ?? null,
            'next_recommended_difficulty' => $response['next_recommendation']['difficulty'] ?? null,
        ]);
    }

    private function putState(Learner $learner, array $state): void
    {
        Cache::put($this->stateCacheKey($learner), $state, now()->addHours(self::STATE_TTL_HOURS));
    }

    private function clearState(Learner $learner): void
    {
        Cache::forget($this->stateCacheKey($learner));
    }

    private function incrementUnclearAttempts(Learner $learner): int
    {
        $key = $this->unclearCacheKey($learner);
        $attempts = Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, now()->addHours(self::STATE_TTL_HOURS));

        return $attempts;
    }

    private function clearUnclearAttempts(Learner $learner): void
    {
        Cache::forget($this->unclearCacheKey($learner));
    }

    private function stateCacheKey(Learner $learner): string
    {
        return "diagnostic_state:{$learner->id}";
    }

    private function unclearCacheKey(Learner $learner): string
    {
        return "diagnostic_unclear_attempts:{$learner->id}";
    }
}

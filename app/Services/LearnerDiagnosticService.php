<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\ReadingSession;
use App\Support\DiagnosticPlacement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

/**
 * The first-login reading check: an adaptive staircase up a ladder of curriculum
 * content, starting where the Parent said the CHILD is, not where their grade
 * says they should be (see DiagnosticPlacement and config/diagnostic.php). A
 * child who cannot read yet starts on letters at any grade; one who reads well
 * starts on a passage.
 *
 * Every item comes from the curated, curriculum-coded bank (DiagnosticBank), so
 * the check opens at once and every child on a rung reads the same reviewed
 * material. The AI generator is no longer involved: it used to write each item
 * live, which took from eight seconds to over a minute and sometimes gave a
 * Grade 1 child text that was too long or only a list of words.
 *
 * Extracted out of LearnerDiagnosticController (previously `private` methods
 * returning Blade Views) for the same reason as LearnerReadingService: one real
 * implementation, called by both the web controller and the mobile API
 * controller.
 *
 * The staircase's in-progress state (which rung/variant is current, how many
 * items done, accuracy history) and the "3 unclear attempts" counter both live
 * in the database cache store, keyed by learner_id, so a token-authenticated
 * mobile request (which carries no session cookie) sees the exact same state a
 * web request would.
 */
class LearnerDiagnosticService
{
    // A check that was already running when the ladder replaced the three
    // tiers has no ladder saved; it finishes on the tiers it started with.
    private const LEGACY_TIERS = ['easy', 'medium', 'hard'];

    // Bump when the shape of the saved state changes.
    private const STATE_VERSION = 2;

    // Warm and non-numeric. No emoji: the result screens no longer use them as decoration.
    private const MASTERY_TO_RESULT_LABEL = [
        'Beginning' => 'You\'re a Rising Reader!',
        'Developing' => 'You\'re a Growing Reader!',
        'Proficient' => 'You\'re a Super Reader!',
    ];

    private const MAX_PASSAGES = 3;

    public const MAX_UNCLEAR_ATTEMPTS = 3;

    private const STATE_TTL_HOURS = 6;

    /**
     * Starts the check for a child (or returns the one already in progress): looks up the bank,
     * rotates each rung's variants by child so two children on one device do not read the same
     * words, and saves the starting state. Kept under its old name because the web controller and
     * the mobile API both call it; there is nothing to generate any more.
     */
    public function ensureBundleGenerated(Learner $learner): array
    {
        $existing = $this->diagnosticState($learner);
        if ($existing !== null) {
            return $existing;
        }

        $ladder = DiagnosticPlacement::ladder();
        $start = DiagnosticPlacement::startingRung($learner);

        $activityIds = [];
        foreach (app(DiagnosticBank::class)->idsByRung() as $rung => $ids) {
            $shift = ($learner->id + array_search($rung, $ladder, true)) % max(1, count($ids));
            $activityIds[$rung] = array_values(array_merge(array_slice($ids, $shift), array_slice($ids, 0, $shift)));
        }

        $state = [
            'version' => self::STATE_VERSION,
            'rungs' => $ladder,
            'activity_ids' => $activityIds,
            'variant_used' => array_fill_keys($ladder, 0),
            // Where the Parent said the child is, not a neutral default.
            'current_tier' => $start,
            'passages_done' => 0,
            'level_before' => $learner->mastery_level,
            'accuracy_history' => [],
            // Captured once, right now, before any passage of THIS
            // diagnostic run creates a real ReadingSession row — the
            // staircase can take 1-3 passages, each persisting its own
            // row, so checking "session count === 1" at finish time (as
            // an earlier version of this code did) would only catch the
            // 1-passage case, never a genuine first-time Learner whose
            // diagnostic took 2 or 3 passages to conclude. This flag is
            // the one honest, order-independent way to know.
            'is_first_ever_reading' => ReadingSession::where('learner_id', $learner->id)->count() === 0,
        ];

        $this->putState($learner, $state);

        return $state;
    }

    /**
     * What the passage screen shows for the current item, so a row of letters
     * and a phonics passage are told apart in one place (the web screen and
     * the mobile API both use it).
     *
     * @return array{kind: string, label: string, prompt: string, micLabel: string, doneLabel: string, letters: list<array{upper: string, lower: string}>}
     */
    public function presentation(Activity $activity): array
    {
        if ($activity->isLetterCheck()) {
            return [
                'kind' => 'letters',
                'label' => 'Letters',
                'prompt' => 'Say the name of each letter.',
                'micLabel' => 'Tap the mic, then say each letter out loud!',
                'doneLabel' => "I'm done!",
                'letters' => collect(explode(' ', trim((string) $activity->passage_text)))
                    ->filter()
                    ->map(fn (string $letter) => ['upper' => strtoupper($letter), 'lower' => strtolower($letter)])
                    ->values()
                    ->all(),
            ];
        }

        $direction = trim((string) $activity->instructions);

        return [
            'kind' => 'passage',
            'label' => 'Passage',
            // The generator writes the direction that fits the item ("Read the
            // short words out loud."), so use it rather than a generic line.
            'prompt' => $direction !== '' ? $direction : 'Read this out loud.',
            'micLabel' => 'Tap the mic, then read the words above out loud!',
            'doneLabel' => "I'm done reading!",
            'letters' => [],
        ];
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
        $outcome = $readingAi->analyze($audio, $activity);

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

        // Words the app did not hear well are "not sure", left out of the accuracy (see NotSure).
        return $this->applyStaircaseStep($learner, $state, $activity, \App\Support\NotSure::apply($outcome['result']));
    }

    /**
     * >=90% moves up a rung (stop if already at the top), <70% moves down a
     * rung (stop if already at the bottom), 70-89% stops right here regardless
     * of item count. Capped at 3 items regardless of outcome.
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
            'level_after' => DiagnosticPlacement::masteryOf($tier),
            'flagged_needs_attention' => $accuracy < 70,
            'session_type' => 'Diagnostic',
            'initiated_by' => 'Parent',
        ]);

        $state['passages_done']++;
        $state['accuracy_history'][] = ['tier' => $tier, 'accuracy' => $accuracy];

        $rungs = $state['rungs'] ?? self::LEGACY_TIERS;
        $tierIndex = array_search($tier, $rungs, true);
        $tierIndex = $tierIndex === false ? 0 : $tierIndex;
        $nextTier = null;

        if ($accuracy >= 90) {
            $nextTier = $rungs[$tierIndex + 1] ?? null;
        } elseif ($accuracy < 70) {
            $nextTier = $tierIndex > 0 ? $rungs[$tierIndex - 1] : null;
        }

        $reachedCap = $state['passages_done'] >= self::MAX_PASSAGES;

        if ($nextTier === null || $reachedCap) {
            return $this->finishDiagnostic($learner, $tier, $accuracy, $state['is_first_ever_reading']);
        }

        // A rung the child has already read gets its next text, never the same one twice. A rung
        // they have not been on yet starts with its first.
        $visited = in_array($nextTier, array_column($state['accuracy_history'], 'tier'), true);
        if ($visited) {
            $last = max(0, count($state['activity_ids'][$nextTier] ?? []) - 1);
            $state['variant_used'][$nextTier] = min(($state['variant_used'][$nextTier] ?? 0) + 1, $last);
        }
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

    private function finishDiagnostic(Learner $learner, string $landedTier, float $lastAccuracy, bool $isFirstEverReading): array
    {
        $finalLevel = DiagnosticPlacement::masteryOf($landedTier);

        $update = ['mastery_level' => $finalLevel];

        // Where on the ladder the child landed: what their four step reading path (Letter
        // Explorer ... Story Reader) is worked out from. A check that was already running on the
        // old three tiers has no rung to save; the step is then estimated from the level.
        $rungIndex = array_search($landedTier, DiagnosticPlacement::ladder(), true);
        if ($rungIndex !== false) {
            $update['reading_rung'] = $rungIndex;
            $update['rung_changed_at'] = now(); // readings after this day count toward the next step
        }

        $learner->update($update);

        Notification::notifyForLearner(
            $learner,
            Notification::TYPE_LEVEL_CONFIRMED,
            "{$learner->first_name}'s starting reading level has been confirmed: {$finalLevel}.",
            includeTeacher: false
        );

        // Where the child landed and how well they did there, in the adaptive
        // recommender's own scale, not the raw accuracy of whichever item was
        // last (which cannot tell "names every letter" from "reads sentences").
        $this->initializeAdaptiveRecommendation(
            $learner,
            DiagnosticPlacement::score($landedTier, $lastAccuracy),
            $landedTier
        );

        $this->clearState($learner);

        // Real badge check, replacing diagnostic-results.blade.php's old
        // unconditional "You earned your first badge!" text — genuinely
        // awarded only when this diagnostic really was this Learner's
        // very first reading. $isFirstEverReading is captured once, in
        // ensureBundleGenerated(), before any of THIS diagnostic run's
        // own passages created a ReadingSession row — not re-derived
        // from the current session count here, which would already
        // include 1-3 rows from this same diagnostic by this point.
        $newBadges = app(BadgeService::class)->checkAfterDiagnosticFinish($learner->fresh(), $isFirstEverReading);

        return [
            'status' => 'finished',
            'learner' => $learner->fresh(),
            'finalLevel' => $finalLevel,
            'resultLabel' => self::MASTERY_TO_RESULT_LABEL[$finalLevel],
            'newBadges' => $newBadges,
        ];
    }

    private function initializeAdaptiveRecommendation(Learner $learner, float $placementScore, ?string $rung = null): void
    {
        app(AdaptiveLearningService::class)->initializeFromDiagnostic($learner, $placementScore, $rung);
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

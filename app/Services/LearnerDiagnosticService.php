<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\ReadingSession;
use App\Support\DiagnosticPlacement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * The first-login reading check: an adaptive staircase up a ladder of curriculum
 * content, starting where the Parent said the CHILD is, not where their grade
 * says they should be (see DiagnosticPlacement and config/diagnostic.php). A
 * child who cannot read yet starts on letters at any grade; one who reads well
 * starts on a passage. Extracted out of
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
    // A check that was already running when the ladder replaced the three
    // tiers has no ladder saved; it finishes on the tiers it started with.
    private const LEGACY_TIERS = ['easy', 'medium', 'hard'];

    // Bump when the shape of the saved state changes.
    private const STATE_VERSION = 2;

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

        $ladder = DiagnosticPlacement::ladder();
        $start = DiagnosticPlacement::startingRung($learner);

        // Only the content this child can actually reach from where they start
        // (at most three items, one step each), so a child who starts on
        // letters does not wait for passages they will never be shown.
        $reachable = DiagnosticPlacement::reachableRungs($start);
        $groups = DiagnosticPlacement::generationGroups($reachable);

        $activityIds = array_fill_keys($ladder, []);

        if ($groups !== []) {
            $competencies = config('activity_competencies.competencies');

            // Every reading item is real curriculum content: the generator
            // aligns each bundle to the MATATAG guide for the grade it is asked
            // for. That grade is the grade of the CONTENT the rung needs (Grade 1
            // phonics for a beginner, Grade 2 passages for a reader), never the
            // child's own grade. The requests run at the same time, since each
            // takes several seconds.
            $payloads = [];
            foreach (array_keys($groups) as $i => $groupKey) {
                $payloads['g'.$i] = [
                    'grade' => $groups[$groupKey]['grade'],
                    'competency' => $groups[$groupKey]['competency'],
                    'activity_type' => $groups[$groupKey]['activity_type'],
                    'variants_per_level' => 2,
                    'topic' => null,
                ];
            }

            try {
                // Each request normally answers in under ten seconds, so one that
                // has not after 70 is stuck: try it again rather than have a child
                // wait two and a half minutes for nothing (seen twice in testing).
                $bundles = app(ActivityAiClient::class)->generateBundles($payloads, 70, 2);
            } catch (\RuntimeException $e) {
                throw ValidationException::withMessages(['diagnostic' => $e->getMessage()]);
            }

            foreach (array_values($groups) as $i => $group) {
                $data = $bundles['g'.$i];

                $activities = Activity::createManyFromBundle($data, [
                    'created_by_teacher_id' => null,
                    'grade_level' => 'Grade '.$group['grade'],
                    'competency' => $group['competency'],
                    'competency_label' => $data['competency_label'] ?? $competencies[$group['competency']]['label'],
                    'activity_type' => $group['activity_type'],
                    'purpose' => 'diagnostic',
                ]);

                foreach ($activities as $activity) {
                    $rung = $group['tiers'][strtolower($activity->difficulty_tier)] ?? null;
                    if ($rung !== null) {
                        $activityIds[$rung][] = $activity->id;
                    }
                }
            }
        }

        if (in_array(DiagnosticPlacement::LETTERS, $reachable, true)) {
            $activityIds[DiagnosticPlacement::LETTERS] = $this->letterCheckActivityIds();
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
     * The two letter rows of the letters rung, made once and shared by every
     * child (they are fixed content, not generated per learner). Looked up by
     * their exact letters, so changing config/diagnostic.php makes new rows
     * instead of silently showing the old letters.
     *
     * @return list<int>
     */
    private function letterCheckActivityIds(): array
    {
        $ids = [];

        foreach (config('diagnostic.letters.sets') as $set) {
            $letters = $set['letters'];

            $activity = Activity::firstOrCreate(
                [
                    'purpose' => 'diagnostic',
                    'activity_type' => 'phonics',
                    'grade_level' => 'Grade '.config('diagnostic.ladder.letters.grade'),
                    'variant_label' => $set['label'],
                    'reference_text' => strtolower(implode(' ', $letters)),
                ],
                [
                    'created_by_teacher_id' => null,
                    'competency' => 'foundational_reading',
                    'competency_label' => 'Foundational Reading',
                    'difficulty_tier' => 'Easy',
                    'bundle_title' => 'Letter Check',
                    'title' => 'Letter Check '.$set['label'],
                    'instructions' => 'Say the name of each letter.',
                    'passage_text' => implode(' ', $letters),
                    'word_count' => count($letters),
                    'target_skills' => ['Naming letters'],
                    'reading_features' => ['Single letters'],
                    'follow_up_questions' => [],
                    'status' => 'Draft',
                ]
            );

            $ids[] = $activity->id;
        }

        return $ids;
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

        return $this->applyStaircaseStep($learner, $state, $activity, $outcome['result']);
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

    private function finishDiagnostic(Learner $learner, string $landedTier, float $lastAccuracy, bool $isFirstEverReading): array
    {
        $finalLevel = DiagnosticPlacement::masteryOf($landedTier);

        $learner->update(['mastery_level' => $finalLevel]);

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
            DiagnosticPlacement::score($landedTier, $lastAccuracy)
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

    private function initializeAdaptiveRecommendation(Learner $learner, float $placementScore): void
    {
        app(AdaptiveLearningService::class)->initializeFromDiagnostic($learner, $placementScore);
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

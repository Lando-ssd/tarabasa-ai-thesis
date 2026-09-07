<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\ReadingSession;
use App\Services\ActivityAiClient;
use App\Services\AdaptiveRecommendatorClient;
use App\Services\ReadingAiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LearnerDiagnosticController extends Controller
{
    private const TIERS = ['easy', 'medium', 'hard'];

    private const TIER_TO_MASTERY = [
        'easy' => 'Beginning',
        'medium' => 'Developing',
        'hard' => 'Proficient',
    ];

    /**
     * The result screen's friendly, non-numeric level pill — docs/design-
     * reference-html/tarabasa-learner-diagnostic__1_.html's own Result step
     * shows exactly "🌱 You're a Growing Reader!" for a Developing landing,
     * never a raw score (Part 4.2: "no visible score or pass/fail framing,
     * ever... a child placed at Beginning should feel exactly as celebrated
     * as one placed at Proficient"). Extended here to the other two tiers in
     * the same warm, growth-themed voice the prototype established.
     */
    private const MASTERY_TO_RESULT_LABEL = [
        'Beginning' => '🌱 You\'re a Rising Reader!',
        'Developing' => '🌿 You\'re a Growing Reader!',
        'Proficient' => '🌟 You\'re a Super Reader!',
    ];

    private const MAX_PASSAGES = 3;

    private const MAX_UNCLEAR_ATTEMPTS = 3;

    /**
     * Sprint 4 Slice 4 — the first-login diagnostic
     * (TaraBasaAI_PlacementDiagnostic_Addition.txt +
     * TaraBasaAI_AdaptiveDiagnostic_Correction.txt). PROVISIONAL scope
     * cuts recorded prominently in CLAUDE.md: the staircase adapts
     * WITHIN the Learner's own grade only (gemini_activity_gen's
     * difficulty bands are grade-locked, not truly cross-grade), and
     * every Learner starts at Medium regardless of their Parent
     * placement-quiz pattern (the raw q1/q2/q3 answers were never
     * persisted anywhere, so "were they unanimous" can't be recovered).
     *
     * Intro screen — Part 4.1: never use the word "test," frame this as
     * "Let's Read Together."
     */
    public function show(Request $request): View
    {
        $learner = $request->user('learner');

        $this->ensureBundleGenerated($request, $learner);

        return view('learner.diagnostic-intro', ['learner' => $learner]);
    }

    /**
     * The current passage in the staircase, whichever tier/variant that
     * currently is per the session-tracked state.
     */
    public function passage(Request $request): View
    {
        $learner = $request->user('learner');
        $state = $this->ensureBundleGenerated($request, $learner);

        $activity = Activity::findOrFail($this->currentActivityId($state));

        return view('learner.diagnostic-passage', [
            'activity' => $activity,
            'passageNumber' => $state['passages_done'] + 1,
            'maxPassages' => self::MAX_PASSAGES,
        ]);
    }

    public function submitRecording(Request $request, ReadingAiClient $readingAi): View
    {
        $learner = $request->user('learner');
        $state = $this->diagnosticState($request, $learner);

        abort_if($state === null, 403, 'No diagnostic in progress.');

        $activityId = $this->currentActivityId($state);
        $activity = Activity::findOrFail($activityId);

        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:15360'],
        ]);

        $outcome = $readingAi->analyze($validated['audio'], $activity->reference_text ?? $activity->passage_text);

        if ($outcome['unclear']) {
            return $this->handleUnclear($request, $learner, $state);
        }

        $request->session()->forget($this->unclearSessionKey($learner));

        return $this->applyStaircaseStep($request, $learner, $state, $activity, $outcome['result']);
    }

    /**
     * Generates the 6-passage bundle (Easy/Medium/Hard × 2 variants) once
     * per diagnostic attempt via the real gemini_activity_gen service —
     * ONE API call, not up to three, since the adaptivity is in which
     * pre-generated tier gets shown next, not in generating different
     * content based on performance. Real Activity rows (purpose:
     * 'diagnostic', no authoring Teacher — schema.sql's own stated
     * design for these), never assigned via ActivityAssignment (no
     * Teacher is involved), so access is checked against this Learner's
     * own session state instead of the usual isAccessibleByLearner().
     */
    private function ensureBundleGenerated(Request $request, Learner $learner): array
    {
        $existing = $this->diagnosticState($request, $learner);
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
                // Deliberately no topic — a neutral default keeps
                // difficulty comparable across learners, per the
                // confirmed decision (not "Ocean" for one child and
                // "Dinosaurs" for another affecting perceived difficulty).
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
            'current_tier' => 'medium', // always Medium — see class docblock
            'passages_done' => 0,
            'level_before' => $learner->mastery_level,
            'accuracy_history' => [],
        ];

        $request->session()->put($this->stateSessionKey($learner), $state);

        return $state;
    }

    private function currentActivityId(array $state): int
    {
        $tier = $state['current_tier'];
        $variantIndex = $state['variant_used'][$tier];

        return $state['activity_ids'][$tier][$variantIndex];
    }

    /**
     * The adaptive staircase (AdaptiveDiagnostic_Correction.txt Part 1):
     * >=90% moves up a tier (stop if already Hard), <70% moves down a
     * tier (stop if already Easy), 70-89% stops right here regardless of
     * passage count — "this tier is likely their real level." Capped at
     * 3 passages regardless of outcome. Every attempted passage gets its
     * own real Diagnostic-tagged ReadingSession (confirmed decision) —
     * the actual mastery_level update only applies once, when the
     * staircase concludes.
     */
    private function applyStaircaseStep(Request $request, Learner $learner, array $state, Activity $activity, array $result): View
    {
        $accuracy = (float) ($result['accuracy']['accuracy_score'] ?? 0);
        $tier = $state['current_tier'];

        // Every real attempt gets a real record, same honesty standard as
        // Slice 3 — session_type Diagnostic, initiated_by Parent (fixed,
        // per the patch doc: a first login is inherently something a
        // Parent set up, even though the Learner is the one reading).
        // level_before/level_after describe THIS passage's tier-vs-
        // starting-estimate framing, not the Learner's real mastery_level
        // yet — that only updates once, below, when the staircase ends.
        ReadingSession::create([
            'learner_id' => $learner->id,
            'activity_id' => $activity->id,
            'accuracy_percent' => $accuracy,
            'wcpm' => $result['speed']['wcpm'] ?? null,
            // Same real Reading-api fields now captured on the regular
            // reading flow too (LearnerReadingController) — stored here
            // for consistency even though the diagnostic never calls
            // Adaptive_Recommendator's /recommend itself (only /initialize,
            // once, at the very end of the whole staircase).
            'speed_score' => $result['speed']['speed_score'] ?? null,
            'prosody_score' => $result['prosody']['prosody_score'] ?? null,
            'pronunciation_score' => null,
            'fluency_score' => null,
            'mispronunciation_count' => null,
            'skipped_word_count' => $result['accuracy']['deletions'] ?? null,
            'substitution_count' => $result['accuracy']['substitutions'] ?? null,
            'repetition_count' => null,
            'insertion_count' => $result['accuracy']['insertions'] ?? null,
            // Stored for consistency/future use (e.g. a Teacher/Parent
            // detail view) but deliberately never rendered on this
            // diagnostic's own results screen — Part 4.2's "no visible
            // score or pass/fail framing, ever" rule extends to a
            // word-by-word right/wrong breakdown too, not just a number.
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
        // 70-89%: $nextTier stays null — stop right here, regardless of
        // how many passages have been attempted so far.

        $reachedCap = $state['passages_done'] >= self::MAX_PASSAGES;

        if ($nextTier === null || $reachedCap) {
            return $this->finishDiagnostic($request, $learner, $tier, $accuracy);
        }

        // Moving to a tier — if it's already been used once (a genuine
        // reachable case: Medium low -> Easy high -> back to Medium),
        // show its second pre-generated variant so the Learner never
        // reads identical text twice.
        $state['variant_used'][$nextTier] = min($state['variant_used'][$nextTier] + 1, 1);
        $state['current_tier'] = $nextTier;

        $request->session()->put($this->stateSessionKey($learner), $state);

        // A warm interstitial between passages — docs/design-reference-html/
        // tarabasa-learner-diagnostic__1_.html's "Encouragement" step, never
        // shown before this slice. No score, never (Part 4.2).
        return view('learner.diagnostic-encourage', [
            'message' => $state['passages_done'] >= self::MAX_PASSAGES - 1
                ? "One more short one, then we're done!"
                : "Let's see how the next one goes!",
            'passagesDone' => $state['passages_done'],
            'maxPassages' => self::MAX_PASSAGES,
        ]);
    }

    /**
     * "If still ambiguous after 3, use whichever tier the LAST passage
     * was administered at" (Part 1, rule 3) — $landedTier is exactly
     * that, whether the staircase stopped early (70-89%, or hit a
     * confirmed floor/ceiling) or was cut off by the 3-passage cap.
     */
    private function finishDiagnostic(Request $request, Learner $learner, string $landedTier, float $lastAccuracy): View
    {
        $finalLevel = self::TIER_TO_MASTERY[$landedTier];

        $learner->update(['mastery_level' => $finalLevel]);

        // PlacementDiagnostic_Addition.txt Step 7: notify the linked
        // Parent(s) — no Teacher, since a diagnostic never involves one.
        Notification::notifyForLearner(
            $learner,
            Notification::TYPE_LEVEL_CONFIRMED,
            "{$learner->first_name}'s starting reading level has been confirmed: {$finalLevel}.",
            includeTeacher: false
        );

        $this->initializeAdaptiveRecommendation($learner, $lastAccuracy);

        $request->session()->forget($this->stateSessionKey($learner));

        return view('learner.diagnostic-results', [
            'learner' => $learner->fresh(),
            'finalLevel' => $finalLevel,
            'resultLabel' => self::MASTERY_TO_RESULT_LABEL[$finalLevel],
        ]);
    }

    /**
     * Adaptive_Recommendator's /initialize — called exactly once, right
     * as the diagnostic concludes, per its own README ("use after the
     * first-login assessment"). Only ever has a real reading_fluency
     * score to send (the diagnostic hardcodes that one competency —
     * see ensureBundleGenerated's own comment); foundational_reading and
     * reading_comprehension are honestly left unassessed, exactly how
     * the service's own engine expects an unassessed competency to be
     * represented (excluded from rotation until a real score exists for
     * it, not zero/fabricated). Same swallow-and-continue failure
     * handling as the Practice-reading integration point — a Learner
     * must never be blocked from reaching their real results screen over
     * this.
     */
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

    /**
     * Reused pattern from LearnerReadingController's unclear-attempt
     * handling, scoped per Learner (not per Activity — a diagnostic
     * passage is single-use for this Learner's one attempt, unlike a
     * regular assigned Activity multiple Learners could read).
     */
    private function handleUnclear(Request $request, Learner $learner, array $state): View
    {
        $key = $this->unclearSessionKey($learner);
        $attempts = $request->session()->get($key, 0) + 1;

        if ($attempts >= self::MAX_UNCLEAR_ATTEMPTS) {
            $request->session()->forget($key);
            $request->session()->forget($this->stateSessionKey($learner));

            return view('learner.reading-unclear', ['activity' => null, 'final' => true, 'isDiagnostic' => true]);
        }

        $request->session()->put($key, $attempts);

        return view('learner.reading-unclear', ['activity' => null, 'final' => false, 'isDiagnostic' => true]);
    }

    private function diagnosticState(Request $request, Learner $learner): ?array
    {
        return $request->session()->get($this->stateSessionKey($learner));
    }

    private function stateSessionKey(Learner $learner): string
    {
        return "diagnostic_state.{$learner->id}";
    }

    private function unclearSessionKey(Learner $learner): string
    {
        return "diagnostic_unclear_attempts.{$learner->id}";
    }
}

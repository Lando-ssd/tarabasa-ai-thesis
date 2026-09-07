<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\PersonalWordBank;
use App\Models\ReadingSession;
use App\Services\AdaptiveRecommendatorClient;
use App\Services\ReadingAiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class LearnerReadingController extends Controller
{
    private const MASTERY_TIERS = ['Beginning', 'Developing', 'Proficient'];

    private const MAX_UNCLEAR_ATTEMPTS = 3;

    /**
     * Sprint 4 Slice 3 — real ReadingSession persistence, mastery-level
     * adjustment, points/streak, PersonalWordBank, and the results
     * screen. Calls the real, deployed Reading-api — no mock. Badges and
     * Notifications are deliberately deferred (confirmed with the user):
     * badges need real content decisions no doc defines yet, and there's
     * no Teacher/Parent UI to ever display a notification yet either.
     */
    public function submitRecording(Request $request, Activity $activity, ReadingAiClient $readingAi): View
    {
        $learner = $request->user('learner');

        abort_unless($activity->isAccessibleByLearner($learner), 403);

        // Reading-api streams and hard-rejects over 15MB itself (413), but
        // rejecting an oversized file here first is a cheap, fast pre-check
        // that saves the Learner's bandwidth/time on an upload we already
        // know will fail. 15360 KB = 15MB, matching Reading-api's own
        // MAX_UPLOAD_MB exactly (confirmed by reading its real source).
        $validated = $request->validate([
            'audio' => ['required', 'file', 'max:15360'],
        ]);

        $outcome = $readingAi->analyze(
            $validated['audio'],
            // The activity's own reference_text (kept in sync with
            // passage_text on every edit — see ActivityController::
            // update()), not passage_text directly, since reference_text
            // is the field this project designates for AI-scoring
            // alignment specifically.
            $activity->reference_text ?? $activity->passage_text,
        );

        if ($outcome['unclear']) {
            return $this->handleUnclear($request, $learner, $activity);
        }

        // A real attempt got scored — clear any unclear-attempt counter
        // for this Learner+Activity so a future fresh attempt starts clean.
        $request->session()->forget($this->unclearSessionKey($learner, $activity));

        return $this->scoreAndPersist($learner, $activity, $outcome['result']);
    }

    private function handleUnclear(Request $request, Learner $learner, Activity $activity): View
    {
        $key = $this->unclearSessionKey($learner, $activity);
        $attempts = $request->session()->get($key, 0) + 1;

        if ($attempts >= self::MAX_UNCLEAR_ATTEMPTS) {
            $request->session()->forget($key);

            return view('learner.reading-unclear', ['activity' => $activity, 'final' => true]);
        }

        $request->session()->put($key, $attempts);

        return view('learner.reading-unclear', ['activity' => $activity, 'final' => false]);
    }

    private function unclearSessionKey(Learner $learner, Activity $activity): string
    {
        // Keyed by Learner ID too, not just Activity — the underlying PHP
        // session persists across "Switch learner" (only the auth guard
        // is learner-specific), so without this a second Learner on the
        // same device could inherit the first Learner's unclear-attempt
        // count for the same shared Activity.
        return "unclear_attempts.{$learner->id}.{$activity->id}";
    }

    private function scoreAndPersist(Learner $learner, Activity $activity, array $result): View
    {
        $accuracy = (float) ($result['accuracy']['accuracy_score'] ?? 0);
        $wcpm = $result['speed']['wcpm'] ?? null;
        // Both real, already computed by Reading-api on every call
        // (confirmed by reading its real main.py directly) but never
        // persisted before now — needed as real inputs to
        // Adaptive_Recommendator's per-competency scoring below.
        $speedScore = $result['speed']['speed_score'] ?? null;
        $prosodyScore = $result['prosody']['prosody_score'] ?? null;

        $levelBefore = $learner->mastery_level;
        $levelAfter = $this->adjustMasteryLevel($levelBefore, $accuracy);
        $pointsEarned = (int) round($accuracy / 2);

        // Open Repository — real determination now that a Learner can
        // reach an Activity via either a Teacher assignment or a Parent's
        // repository unlock. Never guessed from the client.
        $initiatedBy = $activity->initiatedBySourceFor($learner);

        $session = ReadingSession::create([
            'learner_id' => $learner->id,
            'activity_id' => $activity->id,
            'accuracy_percent' => $accuracy,
            'wcpm' => $wcpm,
            'speed_score' => $speedScore,
            'prosody_score' => $prosodyScore,
            'pronunciation_score' => null,
            'fluency_score' => null,
            'mispronunciation_count' => null,
            'skipped_word_count' => $result['accuracy']['deletions'] ?? null,
            'substitution_count' => $result['accuracy']['substitutions'] ?? null,
            'repetition_count' => null,
            'insertion_count' => $result['accuracy']['insertions'] ?? null,
            'word_feedback' => $result['accuracy']['word_feedback'] ?? null,
            'level_before' => $levelBefore,
            'level_after' => $levelAfter,
            'flagged_needs_attention' => $accuracy < 70,
            'session_type' => 'Practice',
            'initiated_by' => $initiatedBy,
        ]);

        if ($accuracy < 80) {
            $this->recordStrugglingWords($learner, $session, $result);
        }

        $learner->update([
            'mastery_level' => $levelAfter,
            'points' => $learner->points + $pointsEarned,
            'streak' => $learner->streak + 1,
        ]);

        $this->updateAdaptiveRecommendation($learner, $activity, $session, $accuracy, $speedScore, $prosodyScore);

        $this->notifyForSession($learner, $activity, $session);

        $breakdown = $this->buildWordBreakdown($result['accuracy']['word_feedback'] ?? []);

        return view('learner.reading-results', [
            'activity' => $activity,
            'learner' => $learner->fresh(),
            'accuracy' => $accuracy,
            'wcpm' => $wcpm,
            'levelBefore' => $levelBefore,
            'levelAfter' => $levelAfter,
            'levelChanged' => $levelBefore !== $levelAfter,
            'levelWentUp' => $accuracy >= 90,
            'pointsEarned' => $pointsEarned,
            'wordBreakdown' => $breakdown['words'],
            'extraWordsSaid' => $breakdown['extraWordsSaid'],
        ]);
    }

    /**
     * Maps Reading-api's real word_feedback statuses onto the three the
     * results screen actually shows — correct/skip/sub. Reading-api has
     * no "mispronounced" or "repeated" signal at all (word_feedback items
     * carry no confidence, and there's no key linking a word_feedback
     * entry back to a word_timestamps entry to derive one), so those two
     * categories from the design reference are deliberately not built —
     * showing them would mean fabricating a distinction the real data
     * doesn't support. "insertion" (an extra word the child said that
     * isn't in the passage at all) has no passage word to attach an
     * inline highlight to, so those are collected separately instead of
     * forced into the word-by-word list.
     */
    private function buildWordBreakdown(array $wordFeedback): array
    {
        $words = [];
        $extraWordsSaid = [];

        foreach ($wordFeedback as $entry) {
            $status = $entry['status'] ?? 'correct';

            if ($status === 'insertion') {
                if (! empty($entry['spoken'])) {
                    $extraWordsSaid[] = $entry['spoken'];
                }

                continue;
            }

            $words[] = [
                'text' => $entry['reference'] ?? '',
                'status' => match ($status) {
                    'substitution' => 'sub',
                    'deletion' => 'skip',
                    default => 'correct',
                },
                'heard' => $entry['spoken'] ?? null,
            ];
        }

        return ['words' => $words, 'extraWordsSaid' => $extraWordsSaid];
    }

    /**
     * Adaptive_Recommendator integration — a real enhancement, never a
     * hard dependency. Only attempted when the Learner actually has a
     * competency_states baseline (set by LearnerDiagnosticController's
     * initialize() call after their diagnostic — a Learner who completed
     * their diagnostic before this feature existed simply has none, and
     * that's left honestly null rather than fabricated here) and the
     * activity carries real competency/difficulty_tier values. ANY
     * failure — not configured, unreachable, a bad response — is caught
     * and swallowed: the Learner already has their real results, and
     * losing the "what's next" recommendation is never worth blocking on.
     */
    private function updateAdaptiveRecommendation(Learner $learner, Activity $activity, ReadingSession $session, float $accuracy, ?float $speedScore, ?float $prosodyScore): void
    {
        if ($learner->competency_states === null || ! $activity->competency || ! $activity->difficulty_tier) {
            return;
        }

        try {
            $response = app(AdaptiveRecommendatorClient::class)->recommend([
                'student_id' => $learner->id,
                'grade' => (int) substr($learner->grade_level, 6),
                'completed_activity' => [
                    'activity_id' => $activity->id,
                    'bundle_id' => $activity->generation_id,
                    'competency' => $activity->competency,
                    'difficulty' => strtolower($activity->difficulty_tier),
                ],
                'performance' => [
                    'accuracy_score' => $accuracy,
                    'speed_score' => $speedScore,
                    'prosody_score' => $prosodyScore,
                    // No comprehension-quiz feature exists anywhere in
                    // this app yet — honestly null, never fabricated.
                    'comprehension_score' => null,
                ],
                'current_state' => $learner->competency_states,
                'recent_history' => $this->buildAdaptiveRecentHistory($learner),
            ]);
        } catch (\RuntimeException $e) {
            Log::warning('Adaptive Recommendator call failed, continuing without a recommendation', ['error' => $e->getMessage()]);

            return;
        }

        $session->update(['adaptive_attempt_score' => $response['completed_competency_update']['attempt_score'] ?? null]);

        $learner->update([
            'competency_states' => $response['updated_state'],
            'next_recommended_competency' => $response['next_recommendation']['competency'] ?? null,
            'next_recommended_difficulty' => $response['next_recommendation']['difficulty'] ?? null,
        ]);
    }

    /**
     * Adaptive_Recommendator is stateless and expects the caller to
     * supply real recent history on every call — rebuilt here from real
     * past ReadingSession rows, scoped to ones that already went through
     * this same integration (adaptive_attempt_score not null), since
     * those are the only scores actually comparable to the service's own
     * weighted formula. A pre-integration session's plain accuracy_percent
     * isn't the same scale as a competency-specific weighted score, so
     * mixing it in would misrepresent the Learner's history to the engine
     * rather than honestly omitting what it can't meaningfully use.
     * Capped at 10 — the engine's own evidence checks only ever look at
     * the last couple of same-competency entries anyway.
     */
    private function buildAdaptiveRecentHistory(Learner $learner): array
    {
        return ReadingSession::where('learner_id', $learner->id)
            ->whereNotNull('adaptive_attempt_score')
            ->with('activity')
            ->orderBy('timestamp')
            ->get()
            ->filter(fn (ReadingSession $s) => $s->activity?->competency && $s->activity?->difficulty_tier)
            ->map(fn (ReadingSession $s) => [
                'competency' => $s->activity->competency,
                'difficulty' => strtolower($s->activity->difficulty_tier),
                'score' => $s->adaptive_attempt_score,
                'activity_id' => $s->activity_id,
            ])
            ->values()
            ->take(-10)
            ->values()
            ->all();
    }

    /**
     * Step 6: null/"New" (a brand-new Learner) is treated as the
     * Beginning tier position for the purposes of this math — never
     * happens via the current wizard (which always runs the placement
     * quiz), but the column is nullable and this keeps the adjustment
     * well-defined regardless.
     */
    private function adjustMasteryLevel(?string $currentLevel, float $accuracy): string
    {
        $index = array_search($currentLevel, self::MASTERY_TIERS, true);
        if ($index === false) {
            $index = 0;
        }

        if ($accuracy >= 90) {
            $index = min($index + 1, count(self::MASTERY_TIERS) - 1);
        } elseif ($accuracy < 70) {
            $index = max($index - 1, 0);
        }

        return self::MASTERY_TIERS[$index];
    }

    /**
     * Reading-api's real word_feedback array hands us the exact missed
     * words directly — no need to re-derive them. "reference" is the
     * word that was supposed to be read (the one worth drilling), not
     * whatever Vosk misheard it as.
     */
    private function recordStrugglingWords(Learner $learner, ReadingSession $session, array $result): void
    {
        $missed = collect($result['accuracy']['word_feedback'] ?? [])
            ->filter(fn ($word) => ($word['status'] ?? 'correct') !== 'correct')
            ->pluck('reference')
            ->filter()
            ->unique()
            ->take(2);

        foreach ($missed as $word) {
            PersonalWordBank::create([
                'learner_id' => $learner->id,
                'session_id' => $session->id,
                'word' => $word,
                'mastery_status' => 'Struggling',
            ]);
        }
    }

    /**
     * Learner Actor Prompt Step 6: "once a real score comes back," notify
     * the Learner's Teacher (if in a class — this checks whether the
     * LEARNER has a Teacher via their class, not whether this particular
     * session was Teacher-initiated, so a Parent-initiated Repository
     * session still reaches the class Teacher) and every linked Parent
     * with the session summary, plus an additional urgent one to both if
     * flagged. Scoped to real Practice sessions only — the diagnostic's
     * own per-passage sessions get their own single "afterward" notice
     * from LearnerDiagnosticController::finishDiagnostic() instead, so
     * a 3-passage diagnostic doesn't spam 3 session-summary notifications
     * before the one real "level confirmed" notice at the end.
     */
    private function notifyForSession(Learner $learner, Activity $activity, ReadingSession $session): void
    {
        $accuracy = round($session->accuracy_percent);
        $summary = "{$learner->first_name} read \"{$activity->title}\" — {$accuracy}% accuracy";
        if ($session->level_before !== $session->level_after) {
            $summary .= ", and moved to {$session->level_after}!";
        } else {
            $summary .= '.';
        }

        Notification::notifyForLearner($learner, Notification::TYPE_SESSION_SUMMARY, $summary);

        if ($session->flagged_needs_attention) {
            Notification::notifyForLearner(
                $learner,
                Notification::TYPE_NEEDS_ATTENTION,
                "{$learner->first_name} needs attention — their session on \"{$activity->title}\" scored {$accuracy}% and was flagged for extra support."
            );
        }
    }
}

<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\PersonalWordBank;
use App\Models\ReadingSession;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * The real scoring/mastery/adaptive-recommendation logic behind a Practice
 * reading submission — extracted out of LearnerReadingController (which
 * used to keep all of this as `private` methods returning Blade Views
 * directly) so the same, single implementation can be called by both the
 * web controller (renders a Blade view) and the mobile API controller
 * (renders JSON). Nothing about the scoring math, mastery thresholds, or
 * Adaptive_Recommendator calls changed in this extraction — every method
 * here is a straight move, not a rewrite. See CLAUDE.md's "Mobile API
 * layer" entry for the reasoning.
 *
 * The "3 unclear attempts" counter used to live in the web session
 * (session()->get/put), which a Sanctum-token-authenticated mobile request
 * never carries. Moved to the database cache store (config/cache.php's
 * default), keyed by learner+activity — works identically for both
 * channels, and is no less correct for the web flow than a session was.
 */
class LearnerReadingService
{
    private const MASTERY_TIERS = ['Beginning', 'Developing', 'Proficient'];

    public const MAX_UNCLEAR_ATTEMPTS = 3;

    private const UNCLEAR_ATTEMPTS_TTL_HOURS = 6;

    /**
     * Runs a real attempt through Reading-api, then either records the
     * unclear-attempt count or scores and persists a real ReadingSession.
     * Returns a plain data array — no View, no HTTP concerns — so both
     * the Blade and JSON controllers render the exact same computed
     * result their own way.
     */
    public function recordAttempt(Learner $learner, Activity $activity, UploadedFile $audio, ?array $answers, ReadingAiClient $readingAi): array
    {
        $comprehension = $this->scoreComprehensionQuiz($activity, $answers);

        $outcome = $readingAi->analyze(
            $audio,
            $activity->reference_text ?? $activity->passage_text,
            $comprehension['score'] ?? null,
        );

        if ($outcome['unclear']) {
            $attempts = $this->incrementUnclearAttempts($learner, $activity);
            $final = $attempts >= self::MAX_UNCLEAR_ATTEMPTS;

            if ($final) {
                $this->clearUnclearAttempts($learner, $activity);
            }

            return ['status' => 'unclear', 'final' => $final];
        }

        // A real attempt got scored — clear any unclear-attempt counter for
        // this Learner+Activity so a future fresh attempt starts clean.
        $this->clearUnclearAttempts($learner, $activity);

        return ['status' => 'scored'] + $this->scoreAndPersist($learner, $activity, $outcome['result'], $comprehension);
    }

    /**
     * My Bookshelf's "Read Again" — a real Reading-api score and the same
     * real word-by-word feedback a normal reading gets, but deliberately
     * skips everything scoreAndPersist() does: no ReadingSession row, no
     * mastery/points/streak change, no PersonalWordBank entries, no
     * notification, no Adaptive_Recommendator call, no badge check. A
     * confirmed decision: re-reading a favorite from the shelf is free
     * practice, the same "clean separation from the real reading-
     * achievement system" reasoning already established for Practice
     * Games — otherwise a Learner could re-read one easy Activity
     * repeatedly to inflate their real level/points/streak. No comprehension
     * quiz either — that's tied to the real scoring pipeline this
     * deliberately bypasses. Never persists an unclear-attempt counter:
     * since nothing is at stake, a Learner can simply try again as many
     * times as they want.
     */
    public function recordFreeReattempt(Activity $activity, UploadedFile $audio, ReadingAiClient $readingAi): array
    {
        $outcome = $readingAi->analyze($audio, $activity->reference_text ?? $activity->passage_text);

        if ($outcome['unclear']) {
            return ['status' => 'unclear'];
        }

        $result = $outcome['result'];
        $accuracy = (float) ($result['accuracy']['accuracy_score'] ?? 0);
        $wcpm = $result['speed']['wcpm'] ?? null;

        $breakdown = $this->buildWordBreakdown(
            $result['accuracy']['word_feedback'] ?? [],
            $result['word_timestamps'] ?? []
        );

        return [
            'status' => 'scored',
            'accuracy' => $accuracy,
            'wcpm' => $wcpm,
            'wordBreakdown' => $breakdown['words'],
            'extraWordsSaid' => $breakdown['extraWordsSaid'],
            'wordsToPractice' => $breakdown['practiceCount'],
        ];
    }

    /**
     * Only meaningful for reading_comprehension-competency Activities —
     * confirmed via gemini_activity_gen's own real source that
     * follow_up_questions ONLY exist for that competency, enforced by its
     * own server-side validation, so every other Activity type simply has
     * none and this returns null immediately. Never trusts a client-
     * submitted score directly: the submitted $answers are raw picked
     * choice text per question index, checked here against the Activity's
     * own real stored `answer` field.
     */
    public function scoreComprehensionQuiz(Activity $activity, ?array $answers): ?array
    {
        $questions = ($activity->competency === 'reading_comprehension') ? ($activity->follow_up_questions ?: []) : [];

        if (empty($questions) || $answers === null) {
            return null;
        }

        $breakdown = [];
        $correctCount = 0;

        foreach ($questions as $i => $question) {
            $picked = $answers[$i] ?? null;
            $isCorrect = $picked !== null && $picked === $question['answer'];
            if ($isCorrect) {
                $correctCount++;
            }

            $breakdown[] = [
                'question' => $question['question'],
                'picked' => $picked,
                'correctAnswer' => $question['answer'],
                'isCorrect' => $isCorrect,
            ];
        }

        $total = count($questions);

        return [
            'score' => $total > 0 ? round(($correctCount / $total) * 100, 2) : null,
            'correctCount' => $correctCount,
            'totalCount' => $total,
            'breakdown' => $breakdown,
        ];
    }

    private function scoreAndPersist(Learner $learner, Activity $activity, array $result, ?array $comprehension = null): array
    {
        $accuracy = (float) ($result['accuracy']['accuracy_score'] ?? 0);
        $wcpm = $result['speed']['wcpm'] ?? null;
        $speedScore = $result['speed']['speed_score'] ?? null;
        $prosodyScore = $result['prosody']['prosody_score'] ?? null;
        $comprehensionScore = $comprehension['score'] ?? null;

        $levelBefore = $learner->mastery_level;
        $levelAfter = $this->adjustMasteryLevel($levelBefore, $accuracy);
        $pointsEarned = (int) round($accuracy / 2);

        $initiatedBy = $activity->initiatedBySourceFor($learner);

        $session = ReadingSession::create([
            'learner_id' => $learner->id,
            'activity_id' => $activity->id,
            'accuracy_percent' => $accuracy,
            'wcpm' => $wcpm,
            'speed_score' => $speedScore,
            'prosody_score' => $prosodyScore,
            'comprehension_score' => $comprehensionScore,
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

        $this->updateAdaptiveRecommendation($learner, $activity, $session, $accuracy, $speedScore, $prosodyScore, $comprehensionScore);

        $this->notifyForSession($learner, $activity, $session);

        $levelChanged = $levelBefore !== $levelAfter;
        $levelWentUp = $accuracy >= 90;

        // Checked after the streak/mastery_level update above, so the
        // service sees the Learner's real new values (a fresh streak of
        // exactly 3, not the pre-increment 2) — and only "genuinely
        // leveled up" (changed AND upward, not a downward move or a
        // same-tier no-op at the Proficient ceiling) earns the badge,
        // the same distinction the results screen itself already draws
        // between levelChanged and levelWentUp.
        $newBadges = app(BadgeService::class)->checkAfterPracticeReading($learner->fresh(), $levelChanged && $levelWentUp);

        $breakdown = $this->buildWordBreakdown(
            $result['accuracy']['word_feedback'] ?? [],
            $result['word_timestamps'] ?? []
        );

        return [
            'activity' => $activity,
            'learner' => $learner->fresh(),
            'accuracy' => $accuracy,
            'wcpm' => $wcpm,
            'levelBefore' => $levelBefore,
            'levelAfter' => $levelAfter,
            'levelChanged' => $levelChanged,
            'levelWentUp' => $levelWentUp,
            'pointsEarned' => $pointsEarned,
            'wordBreakdown' => $breakdown['words'],
            'extraWordsSaid' => $breakdown['extraWordsSaid'],
            'wordsToPractice' => $breakdown['practiceCount'],
            'comprehension' => $comprehension,
            'newBadges' => $newBadges,
        ];
    }

    /**
     * Maps Reading-api's real word_feedback statuses onto the five the
     * results screen shows — correct/skip/mispronounced/sub/repeated. See
     * the original LearnerReadingController history in CLAUDE.md for the
     * full reasoning; unchanged here, just relocated.
     */
    private function buildWordBreakdown(array $wordFeedback, array $wordTimestamps): array
    {
        $words = [];
        $extraWordsSaid = [];
        $repeatedSpokenIndexes = $this->detectRepeatedSpokenIndexes($wordTimestamps);
        $spokenIndex = 0;

        foreach ($wordFeedback as $entry) {
            $status = $entry['status'] ?? 'correct';

            if ($status === 'deletion') {
                $words[] = ['text' => $entry['reference'] ?? '', 'status' => 'skip', 'heard' => null];

                continue;
            }

            if ($status === 'insertion') {
                if (! empty($entry['spoken'])) {
                    $extraWordsSaid[] = $entry['spoken'];
                }

                $spokenIndex++;

                continue;
            }

            $isRepeat = isset($repeatedSpokenIndexes[$spokenIndex]);
            $spokenIndex++;

            if ($status === 'correct') {
                $words[] = [
                    'text' => $entry['reference'] ?? '',
                    'status' => $isRepeat ? 'repeated' : 'correct',
                    'heard' => null,
                ];

                continue;
            }

            $words[] = [
                'text' => $entry['reference'] ?? '',
                'status' => $this->looksLikeMispronunciation($entry['reference'] ?? '', $entry['spoken'] ?? '')
                    ? 'mispronounced'
                    : 'sub',
                'heard' => $entry['spoken'] ?? null,
            ];
        }

        $practiceCount = empty($wordFeedback) ? null : count(array_filter($words, fn (array $w) => $w['status'] !== 'correct'));

        return ['words' => $words, 'extraWordsSaid' => $extraWordsSaid, 'practiceCount' => $practiceCount];
    }

    private function detectRepeatedSpokenIndexes(array $wordTimestamps): array
    {
        $repeated = [];

        for ($i = 1; $i < count($wordTimestamps); $i++) {
            $prev = strtolower(trim($wordTimestamps[$i - 1]['word'] ?? ''));
            $curr = strtolower(trim($wordTimestamps[$i]['word'] ?? ''));

            if ($prev !== '' && $prev === $curr) {
                $repeated[$i - 1] = true;
                $repeated[$i] = true;
            }
        }

        return $repeated;
    }

    private function looksLikeMispronunciation(string $reference, string $spoken): bool
    {
        $reference = strtolower(trim($reference));
        $spoken = strtolower(trim($spoken));

        if ($reference === '' || $spoken === '') {
            return false;
        }

        if (metaphone($reference) === metaphone($spoken)) {
            return true;
        }

        $maxLen = max(strlen($reference), strlen($spoken));

        return $maxLen > 0 && (1 - (levenshtein($reference, $spoken) / $maxLen)) >= 0.5;
    }

    private function updateAdaptiveRecommendation(Learner $learner, Activity $activity, ReadingSession $session, float $accuracy, ?float $speedScore, ?float $prosodyScore, ?float $comprehensionScore = null): void
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
                    'comprehension_score' => $comprehensionScore,
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

    private function incrementUnclearAttempts(Learner $learner, Activity $activity): int
    {
        $key = $this->unclearCacheKey($learner, $activity);
        $attempts = Cache::get($key, 0) + 1;
        Cache::put($key, $attempts, now()->addHours(self::UNCLEAR_ATTEMPTS_TTL_HOURS));

        return $attempts;
    }

    private function clearUnclearAttempts(Learner $learner, Activity $activity): void
    {
        Cache::forget($this->unclearCacheKey($learner, $activity));
    }

    /**
     * Keyed by Learner ID too, not just Activity — the same shared
     * underlying store could otherwise let a second Learner switching in
     * on the same device/session inherit the first Learner's count for
     * the same Activity.
     */
    private function unclearCacheKey(Learner $learner, Activity $activity): string
    {
        return "unclear_attempts:{$learner->id}:{$activity->id}";
    }
}

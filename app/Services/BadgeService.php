<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\LearnerBadge;
use App\Models\ReadingSession;

/**
 * Real Badges — deferred at Sprint 4 for lack of defined content, built
 * for real now. Definitions live in config/badges.php (fixed content,
 * not user-editable); this service is the one place that checks real
 * data against those thresholds and records a genuine `learner_badges`
 * row the first time each is earned. Called from both
 * LearnerReadingService::scoreAndPersist() and
 * LearnerDiagnosticService::finishDiagnostic() — the two real moments a
 * badge can be earned — so the check lives in exactly one place rather
 * than being duplicated at each call site.
 */
class BadgeService
{
    /**
     * After a real Practice reading. $leveledUp should be true only when
     * the mastery tier genuinely changed AND it moved up (not a downward
     * move, and not a same-tier no-op at the Proficient ceiling) — the
     * same distinction reading-results.blade.php itself already draws
     * between `levelChanged` and `levelWentUp`.
     */
    public function checkAfterPracticeReading(Learner $learner, bool $leveledUp): array
    {
        $newlyEarned = [];

        // Safe to derive here (unlike the diagnostic path below): a
        // Practice reading's ReadingSession::create() adds exactly ONE
        // row per call, so "count === 1" at this point genuinely means
        // this was the very first reading this Learner ever completed —
        // there's no multi-row-per-call ambiguity the way the diagnostic
        // staircase has.
        $isFirstEverReading = ReadingSession::where('learner_id', $learner->id)->count() === 1;
        $this->maybeAward($learner, 'first_reading_star', $isFirstEverReading, $newlyEarned);
        $this->maybeAward($learner, 'streak_3', $learner->streak >= 3, $newlyEarned);
        $this->maybeAward($learner, 'streak_7', $learner->streak >= 7, $newlyEarned);
        $this->maybeAward($learner, 'leveled_up', $leveledUp, $newlyEarned);

        $practiceCount = ReadingSession::where('learner_id', $learner->id)
            ->where('session_type', 'Practice')
            ->count();
        $this->maybeAward($learner, 'readings_10', $practiceCount >= 10, $newlyEarned);
        $this->maybeAward($learner, 'readings_25', $practiceCount >= 25, $newlyEarned);

        return $newlyEarned;
    }

    /**
     * After the first-login diagnostic concludes. The diagnostic never
     * touches streak/points (a placement test, not ongoing practice —
     * an existing, separate decision), so the only badge reachable here
     * is First Reading Star, for a Learner whose diagnostic genuinely
     * was their very first reading with us.
     *
     * $isFirstEverReading is passed in rather than derived here — the
     * diagnostic staircase can take 1-3 passages, each persisting its
     * own real ReadingSession row before this method ever runs, so a
     * "session count === 1" check computed at this point would only
     * ever catch the 1-passage case. The caller (LearnerDiagnosticService)
     * captures this once, before any of the current run's own passages
     * exist, which is the only order-independent way to know.
     */
    public function checkAfterDiagnosticFinish(Learner $learner, bool $isFirstEverReading): array
    {
        $newlyEarned = [];

        $this->maybeAward($learner, 'first_reading_star', $isFirstEverReading, $newlyEarned);

        return $newlyEarned;
    }

    private function maybeAward(Learner $learner, string $code, bool $eligible, array &$newlyEarned): void
    {
        if (! $eligible) {
            return;
        }

        // The unique(learner_id, badge_code) constraint is the real
        // backstop; this check just avoids a needless duplicate-insert
        // exception on every subsequent reading once a badge is earned.
        $alreadyEarned = LearnerBadge::where('learner_id', $learner->id)
            ->where('badge_code', $code)
            ->exists();

        if ($alreadyEarned) {
            return;
        }

        LearnerBadge::create([
            'learner_id' => $learner->id,
            'badge_code' => $code,
            'earned_at' => now(),
        ]);

        $newlyEarned[] = array_merge(['code' => $code], config("badges.{$code}"));
    }
}

<?php

namespace App\Support;

use App\Models\Learner;
use App\Models\LearnerBadge;
use App\Services\BadgeService;

/**
 * What a parent sees about one child's week, worked out from the same sources the child's own
 * Home uses, so the two screens can never disagree: the real day streak (Learner::readingDayStreak),
 * this week's Practice readings against the weekly goal (config/reading_goals.php), and the
 * badges the child has earned (BadgeService, config/badges.php).
 *
 * Read-only for the parent. The badge sync only records badges the child's own history has
 * already earned, the same call the child's Badges page makes.
 */
class ChildSummary
{
    /**
     * @return array{dayStreak:int, weeklyCount:int, weeklyTarget:int, weeklyLeft:int, weeklyMet:bool, badgesEarned:int, badgesTotal:int, latestBadges:list<array{name:string, icon:string}>}
     */
    public static function for(Learner $learner): array
    {
        $target = (int) config('reading_goals.weekly_target');
        $count = $learner->thisWeeksPracticeReadingSessions()->count();

        app(BadgeService::class)->sync($learner);

        $definitions = config('badges.badges');
        $earned = LearnerBadge::where('learner_id', $learner->id)->orderByDesc('earned_at')->orderByDesc('id')->get();

        return [
            'dayStreak' => $learner->readingDayStreak(),
            'weeklyCount' => $count,
            'weeklyTarget' => $target,
            'weeklyLeft' => max(0, $target - $count),
            'weeklyMet' => $count >= $target,
            'badgesEarned' => $earned->count(),
            'badgesTotal' => count($definitions),
            'latestBadges' => $earned->take(3)->map(fn (LearnerBadge $badge) => [
                'name' => $definitions[$badge->badge_code]['name'] ?? $badge->badge_code,
                'icon' => config('badge_icons.icons.'.$badge->badge_code, config('badge_icons.fallback')),
            ])->values()->all(),
        ];
    }
}

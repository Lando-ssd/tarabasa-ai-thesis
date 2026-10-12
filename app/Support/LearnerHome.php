<?php

namespace App\Support;

use App\Models\Learner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The numbers on a child's Home: the day streak, the week row of the streak card, this week's goal and
 * the growth chart. One place builds them so the website's Home and the phone API cannot disagree.
 *
 * The goal dial and the growth chart both come from the same collection of this week's readings, so
 * they can never disagree about which readings count or where the week starts. Days and weeks are the
 * child's own (App\Support\LearnerClock), not the server's.
 */
class LearnerHome
{
    /**
     * @return array{
     *   dayStreak:int,
     *   streakWeek:Collection,
     *   weeklyCount:int,
     *   weeklyTarget:int,
     *   weeklyMet:bool,
     *   growthDays:Collection,
     *   growthScale:int
     * }
     */
    public static function for(Learner $learner): array
    {
        $clock = LearnerClock::now();
        $weeklySessions = $learner->thisWeeksPracticeReadingSessions();
        $weeklyCount = $weeklySessions->count();
        $weeklyTarget = (int) config('reading_goals.weekly_target');

        $perDay = $weeklySessions->countBy(fn ($s) => LearnerClock::local($s->timestamp)->dayOfWeekIso);
        $labels = ['M', 'T', 'W', 'T', 'F', 'S', 'S'];

        $growthDays = collect(range(1, 7))->map(fn (int $isoDay) => [
            'label' => $labels[$isoDay - 1],
            'count' => $perDay->get($isoDay, 0),
            'isToday' => $isoDay === $clock->dayOfWeekIso,
            'isFuture' => $isoDay > $clock->dayOfWeekIso,
        ]);

        // The streak popover's week row runs Sunday to Saturday.
        $readingDays = $learner->practiceReadingDays()->flip();
        $sunday = $clock->copy()->startOfWeek(Carbon::SUNDAY);
        $streakWeek = collect(range(0, 6))->map(function (int $offset) use ($sunday, $readingDays) {
            $date = $sunday->copy()->addDays($offset);

            return ['label' => ['S', 'M', 'T', 'W', 'T', 'F', 'S'][$offset], 'done' => $readingDays->has($date->toDateString())];
        });

        return [
            'dayStreak' => $learner->readingDayStreak(),
            'streakWeek' => $streakWeek,
            'weeklyCount' => $weeklyCount,
            'weeklyTarget' => $weeklyTarget,
            'weeklyMet' => $weeklyCount >= $weeklyTarget,
            'growthDays' => $growthDays,
            'growthScale' => max(3, (int) $growthDays->max('count')),
        ];
    }
}

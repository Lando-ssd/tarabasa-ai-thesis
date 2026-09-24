<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * "What day is it for this child?" The app stores timestamps in UTC, but the
 * learners are in the Philippines, so a reading at 7 in the morning there is
 * still the previous day in UTC. Everything that is about calendar days or
 * the hour of the day (the day streak, the weekly goal and chart, the early
 * bird and night owl badges) goes through this instead of the server clock, so
 * a child's "today" and "this week" match their own.
 */
class LearnerClock
{
    public static function timezone(): string
    {
        return config('reading_goals.timezone', 'Asia/Manila');
    }

    /** The current moment, expressed in the learners' timezone. */
    public static function now(): Carbon
    {
        return Carbon::now(self::timezone());
    }

    /** A stored timestamp (UTC), expressed in the learners' timezone. */
    public static function local(mixed $timestamp): Carbon
    {
        return Carbon::parse($timestamp)->setTimezone(self::timezone());
    }

    /** A moment in the learners' timezone, converted back to the storage timezone for database comparisons. */
    public static function toStorage(Carbon $moment): Carbon
    {
        return $moment->copy()->setTimezone(config('app.timezone'));
    }
}

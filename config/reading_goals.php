<?php

/**
 * Weekly Goal's target — a fixed system default, not a Parent/Teacher-
 * editable setting (confirmed with the user: no new UI/migration for
 * this slice, same reasoning as config/badges.php's fixed definitions).
 * Counted against Learner::thisWeeksPracticeReadingSessions() — real
 * Practice sessions only, Diagnostic excluded.
 */
return [
    'weekly_target' => 5,

    // The learners are in the Philippines. Days, weeks and the hour of the day
    // (streak, weekly goal and chart, early bird / night owl badges) are worked
    // out in this timezone, not the server's UTC. See App\Support\LearnerClock.
    'timezone' => 'Asia/Manila',
];

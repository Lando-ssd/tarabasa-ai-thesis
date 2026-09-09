<?php

/**
 * Real Badge definitions — fixed content, not user-editable, so this is
 * a config array rather than a database table (only `learner_badges`,
 * which records which of these a specific Learner has actually earned,
 * gets a real migration). Order here is the display order on the "My
 * Badges" screen.
 *
 * Six badges this slice, all backed by data this app already tracks.
 * A seventh ("Game Explorer," tied to completing a Practice Games
 * session) is a deliberate, disclosed fast-follow — Practice Games are
 * 100% client-side by design and never write anything to the server on
 * completion, so there's no real signal to check yet; adding one needs
 * a small new write path, out of scope for this slice.
 */
return [
    'first_reading_star' => [
        'name' => 'First Reading Star',
        'description' => 'Complete your very first reading with TaraBasa.',
        'emoji' => '🌟',
    ],
    'streak_3' => [
        'name' => '3-Day Streak',
        'description' => 'Read on 3 days in a row.',
        'emoji' => '🔥',
    ],
    'streak_7' => [
        'name' => '7-Day Streak',
        'description' => 'Read on 7 days in a row.',
        'emoji' => '🔥',
    ],
    'leveled_up' => [
        'name' => 'Leveled Up',
        'description' => 'Move up a reading level for the first time.',
        'emoji' => '📈',
    ],
    'readings_10' => [
        'name' => '10 Readings',
        'description' => 'Finish 10 real reading activities.',
        'emoji' => '📖',
    ],
    'readings_25' => [
        'name' => '25 Readings',
        'description' => 'Finish 25 real reading activities.',
        'emoji' => '📚',
    ],
];

<?php

/**
 * What Easy, Medium and Hard mean for an activity, shown to Teachers next to every activity and
 * in the Generate window.
 *
 * The generator (gemini_activity_gen v3.1.0) decides a level from how familiar the words are and
 * how long the text is. Its own response says the levels are application bands, not official
 * DepEd or MATATAG scores, and its author says they cannot be defined exactly, which is why the
 * Teacher validates every draft and decides which level it belongs in.
 *
 * `bands` mirrors that service's READING_LENGTH_BANDS (main.py): [min, max] spoken words a child
 * reads aloud, per activity type, grade and level. It is copied here so the Generate window and
 * the "why this level" box can show a range without a network call. If the service changes its
 * bands, this file needs the matching update (same arrangement as config/activity_competencies).
 */

$word = [
    1 => ['easy' => [5, 7], 'medium' => [8, 10], 'hard' => [11, 14]],
    2 => ['easy' => [6, 8], 'medium' => [9, 12], 'hard' => [13, 16]],
    3 => ['easy' => [8, 10], 'medium' => [11, 14], 'hard' => [15, 20]],
];
$timed = [
    1 => ['easy' => [30, 45], 'medium' => [46, 65], 'hard' => [66, 90]],
    2 => ['easy' => [45, 65], 'medium' => [66, 95], 'hard' => [96, 130]],
    3 => ['easy' => [60, 85], 'medium' => [86, 120], 'hard' => [121, 160]],
];

return [

    'tiers' => ['Easy', 'Medium', 'Hard'],

    // The most of one level a single Generate click can ask for (the service's own limit).
    'max_per_level' => 5,

    'info' => [
        'Easy' => [
            'short' => 'Familiar words, short text',
            'long' => 'Very familiar words that are easy to sound out, in the shortest text for the grade.',
        ],
        'Medium' => [
            'short' => 'Grade level words, longer text',
            'long' => 'Words a child at this grade should know, in text that is a little longer.',
        ],
        'Hard' => [
            'short' => 'Harder words, longest text',
            'long' => 'The hardest words a child at this grade should meet, in the longest text for the grade.',
        ],
    ],

    'bands' => [
        'word_reading' => $word,
        'sight_word_reading' => $word,
        'phonics_reading' => $word,
        'sentence_reading' => [
            1 => ['easy' => [5, 8], 'medium' => [9, 14], 'hard' => [15, 22]],
            2 => ['easy' => [8, 14], 'medium' => [15, 24], 'hard' => [25, 36]],
            3 => ['easy' => [12, 20], 'medium' => [21, 34], 'hard' => [35, 50]],
        ],
        'passage_reading' => [
            1 => ['easy' => [20, 30], 'medium' => [31, 45], 'hard' => [46, 65]],
            2 => ['easy' => [30, 45], 'medium' => [46, 70], 'hard' => [71, 100]],
            3 => ['easy' => [40, 60], 'medium' => [61, 90], 'hard' => [91, 130]],
        ],
        'timed_reading' => $timed,
        'repeated_reading' => $timed,
        'reading_comprehension' => [
            1 => ['easy' => [25, 35], 'medium' => [36, 50], 'hard' => [51, 70]],
            2 => ['easy' => [35, 50], 'medium' => [51, 75], 'hard' => [76, 105]],
            3 => ['easy' => [45, 65], 'medium' => [66, 95], 'hard' => [96, 135]],
        ],
    ],

];

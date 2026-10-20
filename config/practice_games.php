<?php

/**
 * Practice Games: the numbers every game shares, in one place, so the games, the public page "How TaraBasa decides" and
 * the phone app cannot say different things.
 *
 * The website's games read these (the page turns them into the game's script); the phone app is written in TypeScript and
 * cannot read this file, so tests/Feature/PracticeGameRulesTest.php reads the phone's source and fails if its constants
 * differ from the numbers here.
 *
 * Every number is the team's own choice, set with synthesized voices and no real child. None comes from a study; the page
 * says so. Change a number here and the website games, the page and the test move together (the phone code then has to be
 * changed to match, which the test tells you).
 */
return [

    // A sitting is up to this many rounds, or a single round when the child already starts on the top level.
    'rounds' => 3,

    // A round is this many things to read (words, balloons, rows, sentences).
    'per_round' => 3,

    // This many of them read right the first time make a clean round, and the child climbs one level.
    'clean_hits' => 2,

    // Tries for one thing: read it, then once more, then the game moves on. "Not sure" is never "wrong".
    'tries' => 2,

    // The share of the words asked that has to be heard for a row or a sentence to count as read (about two thirds).
    'heard_share' => 0.66,

    // The level a game starts at follows the child's step on the reading path: level 1 below the first number, level 2 from it,
    // level 3 from the second. (Syllable Steps uses the syllable method's own word levels instead: config/syllable_method.php.)
    'level_2_from_step' => 2,
    'level_3_from_step' => 4,
];

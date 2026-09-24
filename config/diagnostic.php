<?php

/**
 * The first-login reading check. It must start where the Parent said the
 * child is: a Grade 1 child described as "just starting" is asked for letters
 * only (no reading), and every reading item is phonics, aligned to the
 * MATATAG curriculum guide the generator holds.
 *
 * The check climbs a ladder of rungs, one item per rung at a time:
 *   letters  (Grade 1 only)  say the names of six letters
 *   easy     phonics reading, the easiest band for the child's grade
 *   medium   phonics reading
 *   hard     phonics reading
 */
return [

    'letters' => [
        // The curriculum guide's own Grade 1 letter competency:
        // RL1PWS-II-1, "Produce the sounds represented by letters" (Phonics
        // and Word Study). There is no letter competency in Grade 2 or 3 (their
        // phonics starts at CVC words), so the letters rung is Grade 1 only.
        'competency_code' => 'RL1PWS-II-1',

        // Six letters per item, in the usual phonics teaching order (s a t p i n
        // first). Two sets, so a child who swings back to this rung is not asked
        // the same letters twice. Letters the speech model reliably confuses
        // (Y heard as "why", Z as "c", X, Q) are left out on purpose.
        'sets' => [
            ['label' => 'A', 'letters' => ['S', 'A', 'T', 'P', 'I', 'N']],
            ['label' => 'B', 'letters' => ['M', 'D', 'O', 'G', 'C', 'B']],
        ],
    ],

    // Where the check starts for each thing a Parent can say about the child's
    // reading, as a position on the ladder: 0 letters, 1 easy, 2 medium, 3 hard.
    // "Not sure" gives no signal. A child whose Parent says they "know letters
    // and sounds" starts at easy phonics words; if that goes badly the check
    // steps down to the letters rung by itself.
    'stage_start' => [
        'starting' => 0,
        'letters' => 1,
        'blending' => 1,
        'sentences' => 2,
        'independent' => 3,
    ],

    // Children added before the Parent's answers were stored have only the
    // level computed from them.
    'legacy_mastery_start' => [
        'Beginning' => 1,
        'Developing' => 2,
        'Proficient' => 2,
    ],

    // The adaptive recommender places a first score by its own bands
    // (GET /config: easy up to 59, medium up to 84, hard above). The reading
    // check knows which rung a child landed on and how well they did there, so
    // it sends a score inside that rung's band rather than the raw accuracy,
    // which on the letters rung would otherwise say "hard" for a child who
    // only knows their letters. If the teammate changes those thresholds,
    // update these edges.
    'placement_bands' => [
        'letters' => [0, 29],
        'easy_after_letters' => [30, 59], // Grade 1, where the letters rung sits below easy
        'easy' => [0, 59],                // Grade 2 and 3
        'medium' => [60, 84],
        'hard' => [85, 100],
    ],
];

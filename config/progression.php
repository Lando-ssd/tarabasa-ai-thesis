<?php

/**
 * When a child moves up or down a step of the reading ladder (config/diagnostic.php, rungs 0 to 6).
 *
 * Why this exists: one reading used to move a child a whole level. A child who read six words perfectly once became a
 * "Sentence Reader" on the spot, because a level change also put them on the lowest step of the next level. A single
 * good reading shows the child read THAT text well; it does not show they have mastered the step. Reading
 * programmes decide on sustained, repeated evidence (oral reading assessment at the Phil-IRI levels uses the accuracy of
 * a whole passage, and fluency practice is repeated reading, National Reading Panel, 2000), so the app now needs
 * several strong readings, of real length, of different activities, before it moves a child up ONE step, and it moves
 * a child down only after two weak readings in a row of a text that was not too long for them.
 *
 * The numbers are the team's own, chosen from those sources and the first check's own texts; they are kept here so they
 * can be changed without touching code.
 */
return [

    // A reading counts toward moving up when its accuracy is at least this (the 90 percent line the app has always used,
    // the bottom of the Phil-IRI instructional band).
    'up_accuracy' => 90,

    // How many such readings (and of how many different activities) are needed, out of the last `window` readings that
    // count, all after the child's last move. The reading that finishes the set is the one that moves them.
    'up_readings' => 3,
    'up_distinct_activities' => 2,
    'window' => 5,

    // A weak reading is under this. Two in a row (after the last move) move a child down one step.
    'down_accuracy' => 70,
    'down_readings' => 2,

    // A reading must be at least this many words to be evidence about the step the child is on (about 60 percent of the
    // words that step reads comfortably, config/activity_fit.php), so reading three words perfectly proves nothing
    // about sentences. Indexed by the step the child is leaving; the top step has nothing above it.
    'min_words' => [0 => 4, 1 => 6, 2 => 10, 3 => 17, 4 => 27, 5 => 45],

    'top_rung' => 6,
];

<?php

return [

    /*
    | "Not sure" (see App\Support\NotSure). A word the speech service marked as a different word but
    | heard below this confidence (0 to 1) is "not sure": left out of the score, the word bank and the
    | alerts instead of being counted wrong.
    |
    | PROVISIONAL: first guesses, set from synthesized voices (a wrong word read by the test voice came
    | back at confidence 0.32, right words at 1.0). Tune with real children's recordings.
    */
    'unsure_confidence' => 0.50,

    // "Not sure" may cover at most this share of the words in a passage, least confident first, so it
    // can never hide a reading that is mostly wrong.
    'unsure_max_share' => 0.25,
];

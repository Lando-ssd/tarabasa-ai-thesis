<?php

/**
 * How long a reading activity may be for a child at each rung of the reading ladder (config/diagnostic.php), so an
 * activity that is far too long for a child cannot be given to them, and the suggestions never offer one.
 *
 * Why this exists: a Teacher could give a 69 word timed reading to a Grade 1 class whose learner could not yet read
 * words. The activity's own level (Easy, Medium, Hard) only says how hard it is FOR ITS GRADE, not whether THIS child can
 * read it. The ladder says where the child is, and it is the MATATAG order of learning to read: names of letters, then
 * sounding out words, then sentences, then stories.
 *
 * The numbers are anchored on the first reading check's own curated texts (config/diagnostic_bank.php), the same texts a
 * child of that rung reads without trouble: rung 0 six letters, rung 1 a list of about six words, rung 2 short
 * sentences of eight to twelve words, rung 3 longer sentences of up to about twenty, rungs 4 to 6 stories of about 30,
 * 60 and 100 words. `comfortable` is about one and a half times that (a little more than the check, still easy);
 * `stretch` about twice (hard but possible with help); above `stretch` it is too long and cannot be assigned.
 *
 * PROVISIONAL: these are the team's own choices from the check's texts, not figures from the curriculum guides. A
 * Grade 1 and a Grade 2 teacher should review them (see CLAUDE.md), and they can be changed here without touching code.
 */
return [

    // rung => [comfortable, stretch], in spoken words the child reads aloud in one activity.
    'limits' => [
        0 => [6, 10],
        1 => [10, 14],
        2 => [16, 24],
        3 => [28, 40],
        4 => [45, 60],
        5 => [75, 100],
        6 => [130, 170],
    ],

    // Connected text (sentences and stories) is harder than a list of single words of the same length for a child who
    // is only learning to sound words out: for rungs 0 and 1 its length counts this many times.
    'connected_text_weight' => 1.6,
    'connected_text_types' => ['sentence_reading', 'passage_reading', 'timed_reading', 'repeated_reading', 'reading_comprehension'],
    'connected_text_rungs' => [0, 1],

    // An activity for a whole class or group is refused when at least this share of the learners whose level is known
    // could not read it (at least one learner). Below it, the Teacher is told how many it is a stretch for.
    'block_share' => 0.2,

];

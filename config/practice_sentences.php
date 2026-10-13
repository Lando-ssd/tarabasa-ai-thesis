<?php

/**
 * The sentences Sentence Stars (a Practice Game) offers, in three levels. A child reads one aloud to catch a star.
 *
 * Written by the team for practice, in plain words a Grade 1 to 3 child meets (the first words of the Grade 1 reading
 * curriculum, RL1CAT-III-1 "read sentences with appropriate speed, accuracy and expression" and RL1VWK-I-3 "read
 * high-frequency words"). A Grade 1 and a Grade 2 teacher should read them before the thesis relies on them.
 *
 * How they were chosen, so they can be extended the same way:
 *  - Level 1: three or four words, one short sentence (the same sort of text as the first reading check's sentence rung).
 *  - Level 2: five to seven words.
 *  - Level 3: eight to twelve words, or two very short sentences.
 *  - Only letters and apostrophes, no numbers and no names: speech recognition has to turn a spoken sentence back into
 *    these exact words, and a name or a number is where it most often goes wrong. Words that sound like another word
 *    (red and read, to and two) are left out for the same reason.
 *
 * The game picks sentences that contain a word the child has been missing first, then a sentence that practises their
 * main kind of mistake, then the rest of the level (see PracticeGameService::sentenceLevels).
 */
return [
    'levels' => [
        1 => [
            'The cat sat.',
            'A dog can run.',
            'I see a pig.',
            'The sun is hot.',
            'We can hop.',
            'A big bus.',
            'The hen is wet.',
            'A fox can jump.',
            'My bed is big.',
            'A bat can fly.',
            'The pig is fat.',
            'We have a hat.',
            'The log is wet.',
            'The sun is up.',
            'The cup is hot.',
            'I can sit.',
        ],
        2 => [
            'The frog sat on a log.',
            'My dog likes to jump up.',
            'We swim in the pond.',
            'The little hen ran home.',
            'A fish can swim fast.',
            'Mom has a big blue bag.',
            'The sun is up in the sky.',
            'We play in the green grass.',
            'The cat sleeps on my bed.',
            'I can see a pink flower.',
            'The duck swims in the water.',
            'Birds sing in the morning.',
            'We eat rice and fish.',
            'The bus stops at school.',
            'My friend has a new kite.',
        ],
        3 => [
            'The little frog jumps into the pond. It makes a big splash.',
            'My family goes to the market every Sunday morning.',
            'The farmer feeds the chickens and the pigs.',
            'The girl likes to read books under the big tree.',
            'We plant seeds in the garden and water them every day.',
            'The rain stopped, and a bright rainbow came out.',
            'After school, we play games with our friends.',
            'The brave puppy ran across the field to find his ball.',
            'Dad cooks rice and chicken for our dinner.',
            'A small turtle walks slowly along the sandy beach.',
            'The teacher gave us a story about a kind lion.',
            'Our class went to the park and flew a kite.',
        ],
    ],
];

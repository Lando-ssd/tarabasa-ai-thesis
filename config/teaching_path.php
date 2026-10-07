<?php

/**
 * How to teach the child at each rung of the reading ladder (config/diagnostic.php, rungs 0 to 6), in the MATATAG
 * order of learning to read: letter names, sounding out words, sentences, then stories.
 *
 * It is the one place the app keeps its teaching advice, so the teacher's learner page, the suggested activities and the
 * progress note all say the same thing. Each rung names:
 *   focus   what the child is learning at this rung, and the curriculum code it comes from (the codes and their wording are
 *           the ones already used by the first reading check, config/diagnostic.php, taken from the MATATAG guides in
 *           docs/manuscript/);
 *   moves   a few things a teacher can do, in order, with the supports that match what the research on early reading shows;
 *   ready   the top rung only: what to do there. For every other rung the app writes this line itself from
 *           config/progression.php, so the teacher is told exactly what the app is measuring;
 *   watch   what to keep an eye on at this rung.
 *
 * The advice is the team's own summary of well known findings, not an official DepEd script: the National Reading Panel
 * (2000) found that systematic phonics instruction and guided repeated oral reading both help early readers, and that
 * letter knowledge and sounding words out come before fluent reading; Ehri (2005) describes how children move from
 * knowing letter sounds, to sounding words out, to reading words by sight; the gradual release of responsibility
 * (Pearson and Gallagher, 1983: the teacher does it, then together, then the child alone) is the pattern the moves follow.
 * A Grade 1 and a Grade 2 teacher should review it before the thesis relies on it.
 */
return [

    'basis' => 'National Reading Panel (2000); Ehri (2005); Pearson and Gallagher (1983); MATATAG Reading and Literacy and English guides.',

    'rungs' => [
        0 => [
            'focus' => 'Naming letters and knowing their sounds (EN2PWS-I-2, RL1PWS-I-2).',
            'moves' => [
                'Teach a few letters at a time, starting with the ones that make the most words (s, a, t, p, i, n).',
                'Say the name and the sound together, point to the letter, and let the child copy you.',
                'Mix quick review of old letters with one or two new ones each day.',
            ],
            'watch' => 'Look-alike letters (b and d, p and q) and letters whose name does not start with their sound.',
        ],
        1 => [
            'focus' => 'Sounding out short words, letter by letter (RL1PWS-I-5, EN2PWS-I-3).',
            'moves' => [
                'Read the first word together, saying each sound slowly, then blend the sounds into the word.',
                'Let the child do the next word alone, and help only if they stop.',
                'Use short word lists of three letters (cat, sun, pig) before words with blends or longer endings.',
            ],
            'watch' => 'Guessing from the first letter. If the child guesses, tap each letter and blend again.',
        ],
        2 => [
            'focus' => 'Reading short sentences of familiar words (RL1CAT-III-1, RL1VWK-I-3).',
            'moves' => [
                'Read the sentence once together, then once alone, so the second read is easier and smoother.',
                'Teach common words that cannot be sounded out (the, was, said) as whole words.',
                'Ask the child to point to each word while they read, to keep their place.',
            ],
            'watch' => 'Reading word by word with no flow. Rereading the same sentence builds smoothness.',
        ],
        3 => [
            'focus' => 'Reading longer sentences with speed, accuracy and expression (RL1CAT-III-1, EN2PWS-I-1).',
            'moves' => [
                'Model reading with expression, then have the child echo you.',
                'Reread the same text two or three times; the National Reading Panel found repeated oral reading with feedback builds fluency.',
                'Notice and teach the sight words the child still stumbles on.',
            ],
            'watch' => 'Dropping the ends of words, and skipping small words (a, the, of).',
        ],
        4 => [
            'focus' => 'Reading a short story and understanding it (EN3CAT-I-1, EN3CAT-I-2).',
            'moves' => [
                'Ask who and what before reading, and one question after (what happened first?).',
                'Let the child read a paragraph alone, then retell it in their own words.',
                'Give the same story again a day later, to make it smooth.',
            ],
            'watch' => 'Reading fast but not remembering. Stop after a sentence and ask what it said.',
        ],
        5 => [
            'focus' => 'Reading stories with expression and answering questions about them (EN3CAT-I-1, EN3CAT-I-2).',
            'moves' => [
                'Ask a question that needs a reason (why did the character do that?), not only a fact.',
                'Have the child read to a partner, with expression for the dialogue.',
                'Offer stories a little longer than before, and a mix of made-up and true texts.',
            ],
            'watch' => 'Names and uncommon words. Teach them before reading so they do not slow the whole story.',
        ],
        6 => [
            'focus' => 'Reading longer stories and informational texts on their own (EN3CAT-I-1, EN3CAT-I-2).',
            'moves' => [
                'Give longer stories and true texts, and let the child choose some themselves.',
                'Ask for a summary and for the main idea.',
                'Invite the child to read aloud to younger learners, which builds confidence and expression.',
            ],
            'ready' => 'This is the top step of the ladder. Keep growing through longer stories and harder questions.',
            'watch' => 'Boredom. Offer variety (a different kind of text) before offering only more length.',
        ],
    ],
];

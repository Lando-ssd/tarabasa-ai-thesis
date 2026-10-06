<?php

/**
 * The first-login reading check.
 *
 * It is about where the CHILD is, not which grade they are enrolled in. The
 * Parent's own description of the child's reading picks where the check starts
 * on a ladder that runs from naming letters up to reading a full passage, and
 * the ladder is the same for every child: a Grade 3 child who cannot read yet
 * starts on letters, a Grade 2 child who reads well starts on a passage.
 *
 * Every rung is fixed, curriculum-coded material from a curated bank that the
 * team wrote and a teacher should review (the letters below, and the texts in
 * config/diagnostic_bank.php). Nothing is written by the AI generator any more,
 * so the check opens at once and every child on a rung reads the same reviewed
 * material. Each rung carries the MATATAG competency code it practises, which is
 * what the scoring service is told and what a teacher sees. The `grade` of a
 * rung is the grade of the CODE and of the content, never the child's own.
 *
 * The rungs, lowest to highest:
 *   0 letters          say the names of six letters
 *   1 words            read short, simple words (CVC)
 *   2 short sentences  read short sentences of familiar words
 *   3 sentences        read longer sentences
 *   4-6 passages       read a story of about 30, 60 and 100 words
 *
 * The rungs are grouped by what the recommender's own bands call them
 * (0-2 easy, 3-4 medium, 5-6 hard), and that is also how a landing rung turns
 * into the app's three levels (Beginning, Developing, Proficient) and into the
 * child's four-step reading path (see App\Support\ReadingLevel).
 *
 * `codes` lists the curriculum codes the rung practises, the FIRST of which is
 * the one sent to the scoring service. The wording of each is the guide's own
 * (Reading and Literacy Grade 1 guide and the English Grades 2 to 10 guide).
 * Rung 0 uses the Grade 2 code "Identify alphabet letter names" because names
 * are what speech recognition can check; the Grade 1 code RL1PWS-I-1 is about
 * the sounds letters make.
 */
return [

    'ladder' => [
        'letters' => [
            'kind' => 'letters',
            'grade' => 1, 'competency' => 'foundational_reading', 'activity_type' => 'phonics', 'tier' => 'easy',
            'codes' => [
                'EN2PWS-I-2' => 'Identify alphabet letter names.',
                'RL1PWS-I-2' => 'Identify the letters in L1.',
            ],
            'mastery' => 'Beginning', 'band' => [0, 19],
        ],
        'phonics_easy' => [
            'kind' => 'bank',
            'grade' => 1, 'competency' => 'foundational_reading', 'activity_type' => 'phonics_reading', 'tier' => 'easy',
            'codes' => [
                'RL1PWS-I-5' => 'Sound out words accurately.',
                'EN2PWS-I-3' => 'Read words accurately and automatically according to word patterns: CVC words.',
            ],
            'mastery' => 'Beginning', 'band' => [20, 39],
        ],
        'phonics_medium' => [
            'kind' => 'bank',
            'grade' => 1, 'competency' => 'reading_fluency', 'activity_type' => 'sentence_reading', 'tier' => 'medium',
            'codes' => [
                'RL1CAT-III-1' => 'Read sentences with appropriate speed, accuracy, and expression.',
                'RL1VWK-I-3' => 'Read high-frequency words accurately for meaning.',
            ],
            'mastery' => 'Beginning', 'band' => [40, 59],
        ],
        'phonics_hard' => [
            'kind' => 'bank',
            'grade' => 1, 'competency' => 'reading_fluency', 'activity_type' => 'sentence_reading', 'tier' => 'hard',
            'codes' => [
                'RL1CAT-III-1' => 'Read sentences with appropriate speed, accuracy, and expression.',
                'EN2PWS-I-1' => 'Identify Grade 2 level-appropriate sight words.',
            ],
            'mastery' => 'Developing', 'band' => [60, 71],
        ],
        'passage_easy' => [
            'kind' => 'bank',
            'grade' => 3, 'competency' => 'reading_fluency', 'activity_type' => 'passage_reading', 'tier' => 'easy',
            'codes' => [
                'EN3CAT-I-1' => 'Read grade level sentences with appropriate speed, accuracy, and expression.',
            ],
            'mastery' => 'Developing', 'band' => [72, 84],
        ],
        'passage_medium' => [
            'kind' => 'bank',
            'grade' => 3, 'competency' => 'reading_fluency', 'activity_type' => 'passage_reading', 'tier' => 'medium',
            'codes' => [
                'EN3CAT-I-1' => 'Read grade level sentences with appropriate speed, accuracy, and expression.',
                'EN3CAT-I-2' => 'Comprehend stories.',
            ],
            'mastery' => 'Proficient', 'band' => [85, 92],
        ],
        'passage_hard' => [
            'kind' => 'bank',
            'grade' => 3, 'competency' => 'reading_fluency', 'activity_type' => 'passage_reading', 'tier' => 'hard',
            'codes' => [
                'EN3CAT-I-1' => 'Read grade level sentences with appropriate speed, accuracy, and expression.',
                'EN3CAT-I-2' => 'Comprehend stories.',
            ],
            'mastery' => 'Proficient', 'band' => [93, 100],
        ],
    ],

    'letters' => [
        // Six letters per item, in the usual phonics teaching order (s a t p i n
        // first). Three sets, so a child who swings back to this rung, or a second
        // child on the same device, is not asked the same letters. Letters the
        // speech model reliably confuses are left out on purpose: Y (heard as
        // "why"), Z ("c"), X, Q, U ("you"), and F directly before H (run together).
        // Each set was read back by two synthesized voices through the real scoring
        // service (see CLAUDE.md).
        'sets' => [
            ['label' => 'A', 'letters' => ['S', 'A', 'T', 'P', 'I', 'N']],
            ['label' => 'B', 'letters' => ['M', 'D', 'O', 'G', 'C', 'B']],
            ['label' => 'C', 'letters' => ['H', 'R', 'E', 'F', 'L', 'K']],
        ],
    ],

    // Where the check starts for each thing a Parent can say about the child's
    // reading, as a position on the ladder above. "Not sure" gives no signal. A
    // child whose Parent says they "know letters and sounds" starts on the
    // first words rung; if that goes badly the check steps down to letters by
    // itself.
    'stage_start' => [
        'starting' => 0,
        'letters' => 1,
        'blending' => 1,
        'sentences' => 3,
        'independent' => 5,
    ],

    // Children added before the Parent's answers were stored have only the
    // level that was computed from them.
    'legacy_mastery_start' => [
        'Beginning' => 1,
        'Developing' => 3,
        'Proficient' => 4,
    ],

    // What a check that was already running when the ladder replaced the old
    // three tiers (easy/medium/hard) still needs to finish.
    'legacy_tiers' => [
        'easy' => ['mastery' => 'Beginning', 'band' => [0, 59]],
        'medium' => ['mastery' => 'Developing', 'band' => [60, 84]],
        'hard' => ['mastery' => 'Proficient', 'band' => [85, 100]],
    ],
];

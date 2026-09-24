<?php

/**
 * MATATAG reading subdomains, as the teammate's Adaptive_Recommendator v2
 * and Reading-api v4 define them (their models.py / main.py), plus the
 * friendly names a Grade 1-3 child, a Parent and a Teacher actually see.
 *
 * PROVISIONAL, flagged for the team: the generator (v3.1) only tags an
 * activity with a GROUPED competency (foundational_reading /
 * reading_fluency / reading_comprehension), not with one MATATAG subdomain,
 * and the recommender can only score certain evidence per subdomain
 * (oral word accuracy -> Phonics and Word Study; comprehension answers ->
 * Comprehending and Analyzing Text; Phonological Awareness and Book and
 * Print Knowledge need backend-scored tasks this app has no activities
 * for yet). The mapping below is the closest honest fit for what the app
 * can measure TODAY. When the generator tags activities per subdomain, set
 * 'activity_subdomain' from that tag instead and delete this mapping.
 */
return [

    // Order matters: the recommender's own GRADE_SUBDOMAINS order.
    'grade_subdomains' => [
        1 => [
            'Phonological Awareness',
            'Phonics and Word Study',
            'Vocabulary and Word Knowledge',
            'Book and Print Knowledge',
            'Comprehending and Analyzing Text',
        ],
        2 => [
            'Phonological Awareness',
            'Phonics and Word Study',
            'Vocabulary and Word Knowledge',
            'Comprehending and Analyzing Text',
        ],
        3 => [
            'Phonics and Word Study',
            'Vocabulary and Word Knowledge',
            'Comprehending and Analyzing Text',
        ],
    ],

    'labels' => [
        'Phonological Awareness' => [
            'label' => 'Sounds and Rhymes',
            'description' => 'Hearing and playing with the sounds inside words.',
        ],
        'Phonics and Word Study' => [
            'label' => 'Sounding Out Words',
            'description' => 'Matching letters to their sounds and blending them into words.',
        ],
        'Vocabulary and Word Knowledge' => [
            'label' => 'Word Power',
            'description' => 'Learning new words and what they mean.',
        ],
        'Book and Print Knowledge' => [
            'label' => 'Book Buddies',
            'description' => 'How books and printed words work.',
        ],
        'Comprehending and Analyzing Text' => [
            'label' => 'Understanding Stories',
            'description' => 'Understanding what you read and answering questions about it.',
        ],
    ],

    // Which subdomain an app activity counts toward. Oral passage and word
    // reading are scored by word accuracy, which the recommender accepts as
    // evidence only under Phonics and Word Study (and Vocabulary for
    // sight/word reading). The oral fluency passages therefore count there
    // rather than under Comprehending and Analyzing Text, which needs answered
    // comprehension items.
    'activity_subdomain' => [
        'foundational_reading' => 'Phonics and Word Study',
        'reading_fluency' => 'Phonics and Word Study',
        'reading_comprehension' => 'Comprehending and Analyzing Text',
    ],

    // Per activity_type overrides (the recommender scores these under
    // Vocabulary and Word Knowledge from oral word accuracy).
    'activity_type_subdomain' => [
        'sight_word_reading' => 'Vocabulary and Word Knowledge',
    ],

    // The first-login diagnostic is an oral passage reading, so its accuracy
    // is the starting estimate for this subdomain.
    'diagnostic_subdomain' => 'Phonics and Word Study',

    // Friendly words for a difficulty. Never a raw score.
    'difficulty_words' => [
        'easy' => 'Just Right',
        'medium' => 'Getting Stronger',
        'hard' => 'Challenging You',
    ],
];

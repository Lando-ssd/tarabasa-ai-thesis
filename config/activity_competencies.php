<?php

/**
 * Mirrors gemini_activity_gen's GROUPED_READING_COMPETENCIES (curriculum.py)
 * exactly — a small, locked, third-party-owned registry. Hardcoded here
 * rather than fetched live from the API's /competencies endpoint on every
 * form load, per the service's own README ("populate the teacher's
 * competency dropdown from the FastAPI curriculum endpoint, OR copy/cache
 * those records") — this is the "copy/cache" option, since the registry is
 * small and not expected to change per-request. If the teammate's service
 * ever changes this registry, this file needs a matching manual update.
 */
return [

    'competencies' => [
        'foundational_reading' => [
            'label' => 'Foundational Reading',
            'description' => 'Builds phonological awareness, phonics and word study, sight/high-frequency word recognition, vocabulary/word knowledge, and accurate decoding.',
            'activity_types' => ['word_reading', 'sight_word_reading', 'phonics_reading'],
        ],
        'reading_fluency' => [
            'label' => 'Reading Fluency',
            'description' => 'Builds accurate, increasingly automatic and expressive oral reading of sentences and connected text.',
            'activity_types' => ['sentence_reading', 'passage_reading', 'timed_reading', 'repeated_reading'],
        ],
        'reading_comprehension' => [
            'label' => 'Reading Comprehension',
            'description' => 'Builds understanding of age-appropriate narrative and informational text, including important details, sequence, relationships, inference, and conclusions.',
            'activity_types' => ['passage_reading', 'reading_comprehension'],
        ],
    ],

    // Matches main.py's ACTIVITY_TYPE_LABELS exactly, for display only.
    'activity_type_labels' => [
        'word_reading' => 'Word Reading',
        'sight_word_reading' => 'Sight Word Reading',
        'phonics_reading' => 'Phonics Reading',
        'sentence_reading' => 'Sentence Reading',
        'passage_reading' => 'Passage Reading',
        'timed_reading' => 'Timed Reading',
        'repeated_reading' => 'Repeated Reading',
        'reading_comprehension' => 'Reading + Comprehension',
    ],

    'max_variants_per_level' => 5,

];

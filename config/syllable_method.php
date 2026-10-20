<?php

/**
 * The syllable method: segment and blend, taught through repeated drill, inside structured (systematic) phonics.
 *
 * Source of the method: the team's own working sheet "TaraBasa Syllable Blending Method" (the Filipino name is
 * "pamamaraang pantig", the syllabic method). In English the closest names are syllable blending and syllable
 * segmenting, taught by repeated drill, within structured phonics. This file is the ONE place the app keeps that
 * method, so the activity writer's note, the teacher's teaching notes, the mistake pattern, the games and the
 * public "How TaraBasa decides" page all say the same thing (App\Support\SyllableMethod reads it).
 *
 * The four steps (the sheet's order):
 *   1. Echo and drill   hear each syllable and repeat it several times (syllable drill, repetition for automaticity)
 *   2. Segment          split the word into its syllables, one tap or clap per syllable
 *   3. Blend            join the syllables into the whole word
 *   4. Read in context  read the word inside a sentence, scored by the speech checker
 * Only step 4 is scored. The pieces of a word are often not words themselves (pic, nic, bas, ket, lem, mel) and a
 * speech recognizer is unreliable on isolated non-words, so a piece is NEVER scored: steps 1 to 3 are listening,
 * tapping and joining, and the recognizer hears the whole word read aloud.
 *
 * Curriculum basis (wording read directly from the MATATAG Grade 1 Reading and Literacy guide in docs/manuscript/):
 *   RL1PA-I-2   "Segment a two-three syllable word into its syllabic parts." (phonological awareness, listening and
 *               speaking, so it needs no letters: a child on the Letters step can clap the parts of a spoken word)
 *   RL1PWS-I-5  "Sound out words accurately." (phonics and word study, the blending that follows)
 * The same guide asks for explicit and systematic instruction and names the Gradual Release of Responsibility
 * (Pearson and Gallagher, 1983; Fisher and Frey, 2008), which is the order the four steps follow: the teacher says
 * it, then it is said together, then the child alone.
 *
 * Two corrections to the first draft of the method are kept here as rules, because they change what is generated:
 *   1. "kami" is two open syllables, CV + CV (CVCV), not CVCVC. The words that are really CVC + VC (CVCVC) are
 *      lemon, melon, cabin, rapid. The MATATAG order of word shapes is CVC, then CVCe, then CVVC, CVCC, then CCVC,
 *      VCV, VCCV, so Grade 1 and 2 words are mostly built from CVC chunks.
 *   2. Filipino syllables always sound the same (ka, mi), but English open syllables change (ba-by, ba-nana). So for
 *      English the method starts with CLOSED syllables (CVC + CVC) and brings open syllables in later.
 * Honest note on 2: structured literacy programmes (Orton-Gillingham based ones, for example) teach closed syllables
 * first as a matter of practice. No study was found that proves the order has to be closed syllables first, so it is
 * kept as the team's choice, to be reviewed by a Grade 1 and a Grade 2 teacher.
 */
return [

    'name' => 'Syllable drill: segment and blend',

    'filipino_name' => 'pamamaraang pantig',

    // How many times each piece is said in the drill (the sheet's "repeat count", cell C2).
    'repeat' => 4,

    'codes' => [
        'segment' => ['code' => 'RL1PA-I-2', 'text' => 'Segment a two-three syllable word into its syllabic parts.'],
        'blend' => ['code' => 'RL1PWS-I-5', 'text' => 'Sound out words accurately.'],
    ],

    'steps' => [
        1 => [
            'key' => 'echo', 'name' => 'Hear and echo',
            'standard' => 'syllable drill, repetition for automaticity',
            'learner' => 'Hears each part, then says it back, several times.',
            'scored' => false,
        ],
        2 => [
            'key' => 'segment', 'name' => 'Split it',
            'standard' => 'syllable segmenting',
            'learner' => 'Splits the word into its parts, one tap or clap for each part.',
            'scored' => false,
        ],
        3 => [
            'key' => 'blend', 'name' => 'Join it',
            'standard' => 'syllable blending',
            'learner' => 'Joins the parts together into the whole word.',
            'scored' => false,
        ],
        4 => [
            'key' => 'context', 'name' => 'Read it in a sentence',
            'standard' => 'application in context',
            'learner' => 'Reads the whole word inside a sentence, out loud. This is the only scored step.',
            'scored' => true,
        ],
    ],

    // The three levels of word, from the sheet's "Word Progression". `rungs` are the steps on the reading path
    // (config/diagnostic.php) the level is for: the level rises as the child's level rises (Beginning, Developing,
    // Proficient). A child on step 0 (Letters) has not met words yet, so they only clap the parts of spoken words.
    'levels' => [
        1 => [
            'name' => 'Two closed syllables', 'pattern' => 'CVC + CVC', 'rungs' => [1, 2],
            'note' => 'Both parts are closed syllables, so each part sounds the same way every time. Start with words whose parts are real small words (sun and set).',
        ],
        2 => [
            'name' => 'A closed syllable, then a silent e syllable', 'pattern' => 'CVC + CVCe', 'rungs' => [3, 4],
            'note' => 'The second part has a silent e (cake, rise, time), which makes its vowel say its name.',
        ],
        3 => [
            'name' => 'A closed syllable, then a vowel and consonant', 'pattern' => 'CVC + VC', 'rungs' => [5, 6],
            'note' => 'These words are CVCVC. A dictionary may divide some of them differently (lem-on or le-mon), so the split shown is the one used for decoding practice.',
        ],
    ],

    // The words. `parts` are the written syllables as used for decoding practice, `sentence` is the word read in context
    // (step 4; plain words only, no names or numbers, because a recognizer is weakest on those). `starter` marks words
    // whose parts are both real small words, the gentlest way in. `alone` is false for a word the speech checker did not
    // reliably hear when it was said ON ITS OWN (kitten, basket, puppet, bedtime: one of two synthesized voices missed it, mitten and muffin
    // were missed by both and were dropped); it is still heard inside its sentence, so it stays in the routine and the sentences, and
    // only the single-word games leave it out.
    'words' => [
        // Level 1: CVC + CVC
        ['word' => 'sunset', 'parts' => ['sun', 'set'], 'level' => 1, 'starter' => true, 'sentence' => 'We watch the sunset.'],
        ['word' => 'hotdog', 'parts' => ['hot', 'dog'], 'level' => 1, 'starter' => true, 'sentence' => 'Dad cooks a hotdog.'],
        ['word' => 'laptop', 'parts' => ['lap', 'top'], 'level' => 1, 'starter' => true, 'sentence' => 'Mom has a laptop.'],
        ['word' => 'napkin', 'parts' => ['nap', 'kin'], 'level' => 1, 'starter' => true, 'sentence' => 'Put the napkin on my lap.'],
        ['word' => 'kitten', 'parts' => ['kit', 'ten'], 'level' => 1, 'starter' => true, 'alone' => false, 'sentence' => 'The kitten is soft.'],
        ['word' => 'picnic', 'parts' => ['pic', 'nic'], 'level' => 1, 'starter' => false, 'sentence' => 'We have a picnic.'],
        ['word' => 'rabbit', 'parts' => ['rab', 'bit'], 'level' => 1, 'starter' => false, 'sentence' => 'The rabbit can hop.'],
        ['word' => 'basket', 'parts' => ['bas', 'ket'], 'level' => 1, 'starter' => false, 'alone' => false, 'sentence' => 'The basket is big.'],
        ['word' => 'ladder', 'parts' => ['lad', 'der'], 'level' => 1, 'starter' => false, 'sentence' => 'A ladder is tall.'],
        ['word' => 'puppet', 'parts' => ['pup', 'pet'], 'level' => 1, 'starter' => false, 'alone' => false, 'sentence' => 'The puppet can talk.'],
        ['word' => 'magnet', 'parts' => ['mag', 'net'], 'level' => 1, 'starter' => false, 'sentence' => 'A magnet is strong.'],
        ['word' => 'pigpen', 'parts' => ['pig', 'pen'], 'level' => 1, 'starter' => true, 'sentence' => 'The pig is in the pigpen.'],
        ['word' => 'cobweb', 'parts' => ['cob', 'web'], 'level' => 1, 'starter' => true, 'sentence' => 'A cobweb is on the wall.'],
        ['word' => 'tennis', 'parts' => ['ten', 'nis'], 'level' => 1, 'starter' => false, 'sentence' => 'I play tennis.'],
        ['word' => 'sudden', 'parts' => ['sud', 'den'], 'level' => 1, 'starter' => false, 'sentence' => 'A sudden rain fell.'],

        // Level 2: CVC + CVCe
        ['word' => 'cupcake', 'parts' => ['cup', 'cake'], 'level' => 2, 'starter' => true, 'sentence' => 'I like a cupcake.'],
        ['word' => 'pancake', 'parts' => ['pan', 'cake'], 'level' => 2, 'starter' => true, 'sentence' => 'Mom makes a pancake.'],
        ['word' => 'sunrise', 'parts' => ['sun', 'rise'], 'level' => 2, 'starter' => true, 'sentence' => 'The sunrise is pink.'],
        ['word' => 'bedtime', 'parts' => ['bed', 'time'], 'level' => 2, 'starter' => true, 'alone' => false, 'sentence' => 'It is bedtime now.'],
        ['word' => 'tadpole', 'parts' => ['tad', 'pole'], 'level' => 2, 'starter' => false, 'sentence' => 'A tadpole can swim.'],

        // Level 3: CVC + VC (CVCVC)
        ['word' => 'lemon', 'parts' => ['lem', 'on'], 'level' => 3, 'starter' => false, 'sentence' => 'The lemon is sour.'],
        ['word' => 'melon', 'parts' => ['mel', 'on'], 'level' => 3, 'starter' => false, 'sentence' => 'A melon is sweet.'],
        ['word' => 'cabin', 'parts' => ['cab', 'in'], 'level' => 3, 'starter' => false, 'sentence' => 'We sleep in a cabin.'],
        ['word' => 'rapid', 'parts' => ['rap', 'id'], 'level' => 3, 'starter' => false, 'sentence' => 'The river is rapid.'],
        ['word' => 'robin', 'parts' => ['rob', 'in'], 'level' => 3, 'starter' => false, 'sentence' => 'A robin sat on the fence.'],
        ['word' => 'salad', 'parts' => ['sal', 'ad'], 'level' => 3, 'starter' => false, 'sentence' => 'I eat a salad.'],
        ['word' => 'visit', 'parts' => ['vis', 'it'], 'level' => 3, 'starter' => false, 'sentence' => 'We visit the farm.'],
    ],

    // The Filipino examples of the sheet. Reference only: the speech checker (Vosk) is English only and the app's activities
    // are in English. Grade 1 reading in the curriculum is in the mother tongue, and a Filipino version would need a Filipino
    // speech model, which the project does not have.
    'filipino_reference' => [
        ['word' => 'kami', 'parts' => ['ka', 'mi']],
        ['word' => 'kita', 'parts' => ['ki', 'ta']],
    ],

    // The two corrections to the first draft, as rules the app follows (see the header of this file).
    'corrections' => [
        'A word like kami is CV + CV (CVCV), not CVCVC. The CVCVC words are lemon, melon, cabin and rapid.',
        'English starts with closed syllables (CVC + CVC). Open syllables (ba-by) come later, because they change in English (ba-by, ba-nana).',
    ],

    // Where this comes from. All found by search or read directly as marked; check the original before quoting any of them in
    // the manuscript. `read` is true only for the sources whose wording was read in full by the team.
    'sources' => [
        ['text' => 'MATATAG Reading and Literacy guide, Grade 1 (DepEd, 2023): competencies RL1PA-I-2 and RL1PWS-I-5, explicit and systematic instruction, and the Gradual Release of Responsibility.', 'read' => true],
        ['text' => 'Pearson and Gallagher (1983) and Fisher and Frey (2008, first edition, ASCD; the year was confirmed in a library catalogue): the Gradual Release of Responsibility, the order of the four steps.', 'read' => false],
        ['text' => 'National Reading Panel (2000, NICHD) and Ehri and others (2001): systematic phonics instruction helps early readers. The panel\'s own findings pages say it helps from kindergarten to Grade 6; the size of the effect is disputed, so the app does not quote a number. The full report was not read.', 'read' => false],
        ['text' => 'Reading Rockets, an article on the six syllable types: closed syllables are the most common syllable type in English text. That makes them a sensible place to start, but the article does not say they must be taught first, and no study was found that proves it. The International Dyslexia Association\'s sheet says only that teaching goes from the easiest ideas to the harder ones; it does not name closed syllables first.', 'read' => false],
        ['text' => 'Liberman and others (1974): young children find it easier to split a spoken word into syllables than into single sounds, which is why the method splits into syllables first.', 'read' => false],
        ['text' => 'Therrien (2004): repeated reading improves fluency and comprehension, the reason for repeating each part in the drill.', 'read' => false],
        ['text' => 'Seymour, Aro and Erskine (2003): English takes longer to learn to read than more regular writing systems, because its syllables are more complex and its spelling less regular. That is the reason the method keeps its first words short and simple. Starting with closed syllables is the team\'s own choice: no study found proves it.', 'read' => false],
        ['text' => 'Hirsh-Pasek and others (2015), Psychological Science in the Public Interest 16(1): children learn best from apps that are active (minds on), engaged, meaningful and social. It is why Syllable Steps does not count the drill taps as learning by themselves.', 'read' => false],
        ['text' => 'A small study of 90 children aged 2 to 8 (PubMed Central article PMC7303424): among the 7 to 8 year olds 83 percent could tap, 40 percent swipe and 30 percent drag and drop, and written prompts were advised against for children 8 and under. The sample is small, so it is a reason to prefer one finger taps and spoken instructions in Syllable Steps, not proof.', 'read' => false],
        ['text' => 'The Filipino syllabic method (the Marungko approach) is described in secondary sources only.', 'read' => false],
    ],

    // What the project does NOT have, said once so nobody assumes otherwise.
    'data' => 'Public recordings of children reading aloud exist (for example CMU Kids and MyST), but none is of Filipino children, and none follows children over time. They could help improve the speech recognizer, which is the teammate\'s service, not these written rules. No recording dataset is used by the app.',
];

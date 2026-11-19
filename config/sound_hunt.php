<?php

/**
 * Sound Hunt: a Practice Game where the child listens and taps. No microphone. Tara asks a question about the sounds in words, the child taps one
 * of three cards (each card says its word when tapped) and confirms. Free play like the other games: no points, streak or level, and the one
 * thing a finished game reports is a game_plays row.
 *
 * WHY THIS GAME EXISTS. Every other Practice Game ends with the child reading out loud, and the speech checker (Vosk) hears WORDS, not sounds, so
 * nothing in the app trained the sounds inside words. The MATATAG Reading and Literacy guide for Grade 1 (DepEd, 2023) lists Phonological Awareness
 * ("oracy for literacy", listening and speaking) in the first quarter:
 *   RL1PA-I-3   Identify rhyming words in nursery rhymes, poems, and chants.            -> level 1, rhymes
 *   RL1PA-I-5   Identify initial sounds (vowels, consonants, and semi-vowels, if any).  -> level 2, first sounds
 *   RL1PWS-I-3  Isolate sounds (consonants and vowels) in a word (beginning and/or ending). -> level 3, last sounds
 * (RL1PA-I-2, segmenting syllables, is Syllable Steps. RL1PA-I-4, saying words that rhyme, needs the microphone and is not in this version.)
 * The three levels also follow how awareness is known to develop, from larger units (a whole word's ending, as in a rhyme) towards single sounds
 * (Anthony and Francis, 2005; Ziegler and Goswami, 2005), and the National Reading Panel's analysis (Ehri and others, 2001) found that teaching
 * children to hear the sounds in words helps their reading. Those studies were found by search and not read in full: `read` is false below.
 *
 * WHAT IT CAN AND CANNOT DO. A device voice (and a text to speech clip) can say a whole word, but cannot say a single sound such as /s/ without
 * adding a vowel ("suh"), so EVERY question and every answer here is a whole word. Pulling a word apart into every sound, or blending sounds,
 * would need sounds recorded by a person and is not in this version.
 *
 * THE WORDS. Short words (consonant, vowel, consonant) with plain spelling, so the first letter is the first sound and the last letter is the
 * last sound, and a rhyme is the same ending. The lists were written by the team: a Grade 1 teacher should read them before the thesis relies on
 * them (tests/Feature/SoundHuntGameTest.php checks the structure, not the teaching). Words that look like they should work but do not (knee,
 * city, ghost, "ck", "x", "ar" words) are left out on purpose.
 *
 * Every number the game reads (rounds, items in a round, clean answers to climb, tries) is shared with the other games in config/practice_games.php.
 */
return [

    // The three levels, the question each one asks, and where it comes from in the curriculum.
    'levels' => [
        1 => ['kind' => 'rhyme', 'name' => 'Rhymes', 'ask' => 'Which one rhymes with', 'code' => 'RL1PA-I-3', 'codeText' => 'Identify rhyming words in nursery rhymes, poems, and chants.'],
        2 => ['kind' => 'first', 'name' => 'First sounds', 'ask' => 'Which one starts like', 'code' => 'RL1PA-I-5', 'codeText' => 'Identify initial sounds (vowels, consonants, and semi-vowels, if any).'],
        3 => ['kind' => 'last', 'name' => 'Last sounds', 'ask' => 'Which one ends like', 'code' => 'RL1PWS-I-3', 'codeText' => 'Isolate sounds (consonants and vowels) in a word (beginning and/or ending).'],
    ],

    // How many different questions are made for each level when the page opens: three rounds of three at one level, with none repeated.
    'pool' => 9,

    // How many cards a question has (the answer and the rest).
    'choices' => 3,

    // Words that end the same: a rhyme. Each family has at least three words.
    'rhymes' => [
        'at' => ['cat', 'hat', 'mat', 'sat', 'bat', 'rat'],
        'an' => ['can', 'fan', 'man', 'pan', 'van', 'ran'],
        'ap' => ['cap', 'map', 'nap', 'tap', 'lap'],
        'ig' => ['pig', 'big', 'dig', 'wig', 'fig'],
        'og' => ['dog', 'log', 'fog', 'hog', 'jog'],
        'op' => ['top', 'hop', 'mop', 'pop'],
        'un' => ['sun', 'bun', 'fun', 'run'],
        'et' => ['pet', 'net', 'wet', 'jet', 'set'],
        'en' => ['hen', 'pen', 'ten', 'men', 'den'],
        'ug' => ['bug', 'hug', 'mug', 'rug', 'jug'],
        'it' => ['sit', 'hit', 'bit', 'fit', 'kit'],
        'ot' => ['pot', 'hot', 'dot', 'cot', 'lot'],
        'ip' => ['lip', 'sip', 'tip', 'hip', 'zip', 'dip'],
        'am' => ['jam', 'ham', 'ram'],
    ],

    // Words that start with the same sound, by the letter that makes it. (c is always the hard sound in these words and there is no k group, so
    // two different groups never share a sound.)
    'first' => [
        's' => ['sun', 'sit', 'sad', 'sock', 'sip', 'six', 'set'],
        'm' => ['map', 'man', 'mop', 'mud', 'mat', 'men', 'mug'],
        'b' => ['bed', 'bus', 'bat', 'big', 'bug', 'box', 'bun'],
        'c' => ['cat', 'cup', 'can', 'cap', 'cot', 'cut'],
        'd' => ['dog', 'dig', 'dad', 'dot', 'den', 'dip'],
        'p' => ['pig', 'pen', 'pan', 'pot', 'pop', 'pet'],
        'h' => ['hen', 'hat', 'hot', 'hop', 'hug', 'hit', 'hut'],
        't' => ['top', 'ten', 'tap', 'tip', 'tub'],
        'r' => ['red', 'run', 'rat', 'rug', 'ram', 'rub'],
        'l' => ['log', 'lip', 'lap', 'leg', 'lid', 'lot'],
        'f' => ['fan', 'fox', 'fit', 'fed', 'fig', 'fun'],
        'j' => ['jam', 'jet', 'jug', 'jog'],
        'n' => ['net', 'nap', 'nut', 'nod'],
        'w' => ['wet', 'wig', 'web', 'win'],
        'g' => ['gum', 'gap', 'get', 'got', 'gas'],
    ],

    // Words that end with the same sound, by the letter that makes it.
    'last' => [
        't' => ['cat', 'pet', 'hat', 'net', 'pot', 'sit', 'bat', 'cut', 'hit', 'nut', 'jet', 'wet'],
        'n' => ['sun', 'hen', 'pan', 'ten', 'man', 'can', 'run', 'bun', 'fan', 'men', 'den', 'pen'],
        'g' => ['dog', 'pig', 'big', 'bug', 'log', 'rug', 'wig', 'hug', 'jug', 'mug', 'fog', 'hog', 'dig'],
        'p' => ['cup', 'top', 'map', 'pop', 'tip', 'hop', 'cap', 'nap', 'lip', 'sip', 'tap', 'mop', 'zip'],
        'd' => ['bed', 'red', 'dad', 'mud', 'sad', 'fed'],
        's' => ['bus', 'gas', 'yes'],
        'm' => ['jam', 'ham', 'ram', 'gum'],
    ],

    // What Tara says besides the words (the text of each short clip). A line is built from these and word clips (see App\Support\SoundHunt::lines).
    // When every clip a line needs exists in public/audio/sound-hunt the game plays the clips (the same voice on every device); otherwise
    // it reads the line with the device's own voice.
    'phrases' => [
        'ask-rhyme' => 'Which one rhymes with',
        'ask-first' => 'Which one starts like',
        'ask-last' => 'Which one ends like',
        'again' => "Let's listen again.",
        'yes' => 'Yes!',
        'and' => 'and',
        'rhyme' => 'rhyme.',
        'start-same' => 'both start the same.',
        'end-same' => 'both end the same.',
        'rhymes-with' => 'rhymes with',
        'starts-like' => 'starts like',
        'ends-like' => 'ends like',
        'next-one' => "Let's try the next one.",
    ],

    // Where the clips live (inside public/) and who made them. The clips are made once, by tools/make-voice-clips.py, and shipped with the game.
    'clips' => [
        'directory' => 'audio/sound-hunt',
        'voice' => 'en-PH-RosaNeural',
        'extension' => 'mp3',
    ],

    // Sources for the page "How TaraBasa decides". `read` says whether the source was read in full by the team.
    'sources' => [
        ['text' => 'MATATAG Reading and Literacy guide, Grade 1 (DepEd, 2023): Phonological Awareness (RL1PA-I-3, RL1PA-I-5) and Phonics and Word Study (RL1PWS-I-3), first quarter.', 'read' => true],
        ['text' => 'Ehri, L. C., Nunes, S. R., Willows, D. M., Schuster, B. V., Yaghoub-Zadeh, Z., and Shanahan, T. (2001). Phonemic awareness instruction helps children learn to read. Reading Research Quarterly, 36(3), 250 to 287.', 'read' => false],
        ['text' => 'Anthony, J. L., and Francis, D. J. (2005). Development of phonological awareness. Current Directions in Psychological Science, 14(5), 255 to 259.', 'read' => false],
        ['text' => 'Ziegler, J. C., and Goswami, U. (2005). Reading acquisition, developmental dyslexia, and skilled reading across languages. Psychological Bulletin, 131(1), 3 to 29.', 'read' => false],
    ],
];

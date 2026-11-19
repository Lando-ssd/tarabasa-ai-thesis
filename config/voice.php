<?php

/**
 * How Tara says the letters of the alphabet (Word Train's letters, and the recorded letter clips).
 *
 * THE PROBLEM THIS SETTLES. A read-aloud voice cannot be given a bare letter: "a" is read as the word "a" (uh) and "i" as the word "I". So a letter
 * is given to the voice as the way its NAME is spelled. The first spelling of the letter A was "ay", and that was a mistake: a Philippine English
 * voice reads "ay" like the Tagalog word "ay", which sounds like "eye", so A and I came out the same (the owner heard exactly that). "ay" is also
 * the English word "aye", which many voices read as "eye". No two letters may be given the same spelling (tests/Feature/VoiceLettersTest.php), and
 * A is no longer "ay".
 *
 *   say   how the NAME of the letter is spelled for a voice that has no recorded clip (the device's own read-aloud voice, on the website and on
 *         the phone). The phone app keeps the same list in mobile/src/gameKit.tsx (LETTER_NAMES); a test fails if the two drift apart.
 *   ipa   the letter's name in the International Phonetic Alphabet. Used only by tools/make-voice-clips.py when it is asked to make the letter clips
 *         with explicit sounds (--letters phoneme), and as the written record of what each spelling is meant to sound like.
 *
 * "eigh" for A is the spelling most English voices read as /eI/ (as in weigh and neigh), but it has been chosen by reading, not by listening: open
 * tools/voice-check.html in a browser, press each letter and hear what this computer's voice does, and change `say` here if one is wrong. The
 * recorded clips (tools/make-voice-clips.py) do not depend on any spelling, so they are the real fix once they exist and have been listened to.
 *
 * A letter's SOUND (/s/, /a/) is not here: a voice cannot say one without adding a vowel (see config/sound_hunt.php). Only the names are.
 */
return [
    'letters' => [
        'a' => ['say' => 'eigh', 'ipa' => 'eɪ'],
        'b' => ['say' => 'bee', 'ipa' => 'biː'],
        'c' => ['say' => 'see', 'ipa' => 'siː'],
        'd' => ['say' => 'dee', 'ipa' => 'diː'],
        'e' => ['say' => 'ee', 'ipa' => 'iː'],
        'f' => ['say' => 'eff', 'ipa' => 'ɛf'],
        'g' => ['say' => 'jee', 'ipa' => 'dʒiː'],
        'h' => ['say' => 'aitch', 'ipa' => 'eɪtʃ'],
        'i' => ['say' => 'eye', 'ipa' => 'aɪ'],
        'j' => ['say' => 'jay', 'ipa' => 'dʒeɪ'],
        'k' => ['say' => 'kay', 'ipa' => 'keɪ'],
        'l' => ['say' => 'el', 'ipa' => 'ɛl'],
        'm' => ['say' => 'em', 'ipa' => 'ɛm'],
        'n' => ['say' => 'en', 'ipa' => 'ɛn'],
        'o' => ['say' => 'oh', 'ipa' => 'oʊ'],
        'p' => ['say' => 'pee', 'ipa' => 'piː'],
        'q' => ['say' => 'cue', 'ipa' => 'kjuː'],
        'r' => ['say' => 'are', 'ipa' => 'ɑɹ'],
        's' => ['say' => 'ess', 'ipa' => 'ɛs'],
        't' => ['say' => 'tee', 'ipa' => 'tiː'],
        'u' => ['say' => 'you', 'ipa' => 'juː'],
        'v' => ['say' => 'vee', 'ipa' => 'viː'],
        'w' => ['say' => 'double you', 'ipa' => 'ˈdʌbəl juː'],
        'x' => ['say' => 'ex', 'ipa' => 'ɛks'],
        'y' => ['say' => 'why', 'ipa' => 'waɪ'],
        'z' => ['say' => 'zee', 'ipa' => 'ziː'],
    ],
];

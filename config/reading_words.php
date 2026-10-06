<?php

/**
 * Word knowledge the app's reading analysis uses (App\Support\ErrorPatterns and
 * App\Support\SpeechNormalizer). Plain lists, so a teacher or the adviser can read and correct them.
 */
return [

    /**
     * High-frequency ("sight") words a Grade 1 to 3 reader is expected to know on sight. Skipping one
     * of these is a different kind of mistake from skipping a long word, and it is practised
     * differently. Based on the Dolch pre-primer to Grade 3 lists (a public word list), trimmed to
     * words of four letters or fewer that carry little meaning on their own.
     * Curriculum basis: RL1VWK-I-3 "Read high-frequency words accurately for meaning".
     */
    'high_frequency' => [
        'a', 'i', 'am', 'an', 'as', 'at', 'be', 'by', 'do', 'go', 'he', 'if', 'in', 'is', 'it', 'me', 'my', 'no', 'of', 'on', 'or',
        'so', 'to', 'up', 'us', 'we', 'all', 'and', 'any', 'are', 'but', 'can', 'did', 'for', 'get', 'had', 'has', 'her', 'him',
        'his', 'how', 'its', 'not', 'now', 'off', 'old', 'one', 'our', 'out', 'put', 'ran', 'saw', 'say', 'see', 'she', 'the',
        'too', 'two', 'use', 'was', 'way', 'who', 'yes', 'you', 'back', 'came', 'come', 'down', 'from', 'give', 'good', 'have',
        'here', 'into', 'just', 'like', 'look', 'made', 'many', 'much', 'must', 'over', 'said', 'some', 'that', 'them', 'then',
        'they', 'this', 'very', 'want', 'well', 'went', 'were', 'what', 'when', 'will', 'with', 'your',
    ],

    /**
     * Words that sound exactly alike. A reading aloud cannot tell them apart, so when the recognizer
     * writes the other one down the child is not marked wrong for it. Only true homophones (the same
     * sound) are here: a word that merely sounds close (ripe and right, bean and been) is a real
     * difference and stays a mistake, to be handled by the "not sure" rule when it is heard faintly.
     */
    'homophones' => [
        ['sun', 'son'], ['see', 'sea'], ['to', 'too', 'two'], ['no', 'know'], ['new', 'knew'], ['one', 'won'],
        ['here', 'hear'], ['there', 'their', "they're"], ['by', 'buy', 'bye'], ['be', 'bee'], ['for', 'four', 'fore'],
        ['hi', 'high'], ['i', 'eye', 'aye'], ['meet', 'meat'], ['night', 'knight'], ['pair', 'pear', 'pare'],
        ['ate', 'eight'], ['bear', 'bare'], ['blue', 'blew'], ['dear', 'deer'], ['flower', 'flour'], ['hair', 'hare'],
        ['hole', 'whole'], ['made', 'maid'], ['mail', 'male'], ['pail', 'pale'], ['plain', 'plane'], ['road', 'rode'],
        ['tail', 'tale'], ['wait', 'weight'], ['way', 'weigh'], ['weak', 'week'], ['wood', 'would'], ['red', 'read'],
        ['right', 'write'], ['sail', 'sale'], ['steal', 'steel'], ['tea', 'tee'], ['toe', 'tow'], ['wear', 'where', 'ware'],
        ['which', 'witch'], ['you', 'ewe', 'yew'], ['our', 'hour'], ['its', "it's"], ['bored', 'board'], ['cent', 'scent', 'sent'],
        ['die', 'dye'], ['fair', 'fare'], ['feat', 'feet'], ['flee', 'flea'], ['grate', 'great'], ['heal', 'heel'],
        ['hall', 'haul'], ['mane', 'main'], ['nose', 'knows'], ['peace', 'piece'], ['pour', 'pore'],
        ['rain', 'reign'], ['rap', 'wrap'], ['ring', 'wring'], ['rose', 'rows'], ['sew', 'so', 'sow'], ['stair', 'stare'],
        ['suite', 'sweet'], ['threw', 'through'], ['waist', 'waste'], ['wail', 'whale'],
    ],

    /**
     * Spelling variants of the same word, and numbers. The recognizer writes "mangos" for "mangoes",
     * "mom" for "mum", and a digit as a word. These are the same word read correctly.
     */
    'variants' => [
        ['mangoes', 'mangos'], ['tomatoes', 'tomatos'], ['potatoes', 'potatos'], ['mom', 'mum', 'mommy', 'mummy'],
        ['grey', 'gray'], ['color', 'colour'], ['okay', 'ok'], ['grandpa', 'granddad', 'grandad'], ['grandma', 'granny'],
        ['dad', 'daddy'], ['tv', 'television'], ['alright', 'allright'],
    ],

    'numbers' => [
        '0' => 'zero', '1' => 'one', '2' => 'two', '3' => 'three', '4' => 'four', '5' => 'five', '6' => 'six', '7' => 'seven',
        '8' => 'eight', '9' => 'nine', '10' => 'ten', '11' => 'eleven', '12' => 'twelve', '13' => 'thirteen', '14' => 'fourteen',
        '15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen', '18' => 'eighteen', '19' => 'nineteen', '20' => 'twenty',
        '30' => 'thirty', '40' => 'forty', '50' => 'fifty', '100' => 'one hundred',
    ],

    /** Consonant pairs a young reader mixes up because the letters look alike. */
    'look_alike_pairs' => [['b', 'd'], ['p', 'q'], ['m', 'w'], ['n', 'u'], ['b', 'p'], ['d', 'q']],

    /** Two-letter consonant blends at the start and end of words. */
    'start_blends' => ['bl', 'br', 'cl', 'cr', 'dr', 'fl', 'fr', 'gl', 'gr', 'pl', 'pr', 'sc', 'sk', 'sl', 'sm', 'sn', 'sp', 'st', 'sw', 'tr', 'tw', 'ch', 'sh', 'th', 'wh'],
    'end_blends' => ['nd', 'nt', 'st', 'mp', 'nk', 'ft', 'lt', 'ld', 'lk', 'sk', 'sp', 'ng', 'ck', 'ch', 'sh', 'th'],
];

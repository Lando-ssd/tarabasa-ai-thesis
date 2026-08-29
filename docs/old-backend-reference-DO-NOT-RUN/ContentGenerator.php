<?php

/**
 * ContentGenerator — a STAND-IN for the real AI/LLM call.
 *
 * There is no live LLM API available in this build/test environment, so this
 * class deterministically enforces every rule the Activity Content Addition
 * document specifies (curriculum grounding, minimum-5-item lists, plausible
 * distractors, grade-appropriate passage length, struggling-word re-serving),
 * without actually calling a model. This lets the surrounding business logic
 * (credits, curriculum matching, assignment, sharing) be fully built and
 * tested now. Swapping this class's internals for a real LLM call later
 * requires no changes to ActivityController or anything else that calls it —
 * the public generate() signature is the seam.
 */
class ContentGenerator
{
    // Real consonant sounds only — never invented/nonsense sounds, per the
    // "plausible distractors" rule (e.g. wrong options for "B" are "D","P", not gibberish).
    const LETTER_SOUNDS = [
        'B' => 'buh', 'M' => 'muh', 'D' => 'duh', 'P' => 'puh', 'T' => 'tuh',
        'S' => 'sss', 'N' => 'nuh', 'L' => 'luh', 'R' => 'ruh', 'F' => 'fff',
        'G' => 'guh', 'H' => 'huh', 'K' => 'kuh', 'J' => 'juh', 'W' => 'wuh',
        'C' => 'kuh', 'V' => 'vvv', 'Y' => 'yuh', 'Z' => 'zzz',
    ];

    // Fallback word pool (with image hints) used only when no sampleVocabulary
    // is seeded for the matched curriculum entry — the AI "choosing its own
    // age-appropriate vocabulary" is mocked here as a fixed pool.
    const DEFAULT_WORD_POOL = [
        ['word' => 'cat', 'imageHint' => 'cat'], ['word' => 'dog', 'imageHint' => 'dog'],
        ['word' => 'sun', 'imageHint' => 'sun'], ['word' => 'mat', 'imageHint' => 'mat'],
        ['word' => 'hat', 'imageHint' => 'hat'], ['word' => 'bed', 'imageHint' => 'bed'],
        ['word' => 'pig', 'imageHint' => 'pig'], ['word' => 'cup', 'imageHint' => 'cup'],
        ['word' => 'box', 'imageHint' => 'box'], ['word' => 'fan', 'imageHint' => 'fan'],
        ['word' => 'map', 'imageHint' => 'map'], ['word' => 'pen', 'imageHint' => 'pen'],
    ];

    /**
     * @param string $gameType one of the 6 defined types
     * @param string $gradeLevel "Grade 1"|"Grade 2"|"Grade 3"
     * @param string $topic matched (or typed) curriculum topic, for passage flavor text
     * @param string|null $skillFocus
     * @param array|null $sampleVocabulary from the matched CurriculumGuide entry, or null
     * @param array $strugglingWords 0-3 words from the target Learner's PersonalWordBank
     * @return array{passage_text: string, content: array}
     */
    public static function generate(
        string $gameType,
        string $gradeLevel,
        string $topic,
        ?string $skillFocus,
        ?array $sampleVocabulary,
        array $strugglingWords
    ): array {
        $wordPool = self::buildWordPool($sampleVocabulary, $strugglingWords);
        $passageText = self::buildPassage($gradeLevel, $topic, $wordPool);

        $content = match ($gameType) {
            'Read Aloud' => ['passageText' => $passageText],
            'Letter-Sound Match' => [
                'passageText' => $passageText,
                'letterSoundPairs' => self::buildLetterSoundPairs(),
            ],
            'Word Builder' => [
                'passageText' => $passageText,
                'targetWords' => self::buildTargetWords($wordPool),
            ],
            'Trace-and-Write' => [
                'passageText' => $passageText,
                'traceWords' => array_column($wordPool, 'word'),
            ],
            'Sentence Scramble' => [
                'passageText' => $passageText,
                'scrambledSentences' => self::buildScrambledSentences($wordPool),
            ],
            'Picture-Word Match' => [
                'passageText' => $passageText,
                'wordImagePairs' => array_map(
                    fn($w) => ['word' => $w['word'], 'imageHint' => $w['imageHint']],
                    $wordPool
                ),
            ],
            default => throw new InvalidArgumentException("Unknown gameType: $gameType. Only the 6 defined types are supported."),
        };

        return ['passage_text' => $passageText, 'content' => $content];
    }

    /**
     * Word sourcing precedence (Activity Content Addition, Part 4-5):
     * 1. sampleVocabulary is the primary source if the matched guide has it.
     * 2. 1-3 of the target Learner's Struggling words are deliberately woven in
     *    alongside new vocabulary, regardless of source — "re-serving" words
     *    this specific child got wrong before.
     * 3. Falls back to the default pool if no sampleVocabulary exists.
     * Always at least 5 distinct words total (the practical minimum).
     */
    private static function buildWordPool(?array $sampleVocabulary, array $strugglingWords): array
    {
        $base = [];
        if (!empty($sampleVocabulary)) {
            foreach ($sampleVocabulary as $w) {
                $base[] = ['word' => $w, 'imageHint' => $w];
            }
        } else {
            $base = self::DEFAULT_WORD_POOL;
        }

        $struggling = array_slice(array_unique($strugglingWords), 0, 3);
        $strugglingEntries = array_map(fn($w) => ['word' => $w, 'imageHint' => $w], $struggling);

        // Merge struggling words in first (so they're guaranteed present), then
        // fill up to at least 5 total distinct words from the base pool.
        $merged = $strugglingEntries;
        $seen = array_map(fn($e) => strtolower($e['word']), $merged);
        foreach ($base as $entry) {
            if (count($merged) >= max(5, count($strugglingEntries))) {
                if (count($merged) >= 5) break;
            }
            if (!in_array(strtolower($entry['word']), $seen, true)) {
                $merged[] = $entry;
                $seen[] = strtolower($entry['word']);
            }
        }
        // Guarantee minimum 5 even if sampleVocabulary was short.
        $i = 0;
        while (count($merged) < 5 && $i < count(self::DEFAULT_WORD_POOL)) {
            $entry = self::DEFAULT_WORD_POOL[$i++];
            if (!in_array(strtolower($entry['word']), $seen, true)) {
                $merged[] = $entry;
                $seen[] = strtolower($entry['word']);
            }
        }

        return $merged;
    }

    private static function buildPassage(string $gradeLevel, string $topic, array $wordPool): string
    {
        $words = array_column($wordPool, 'word');
        $w = fn($i) => $words[$i % count($words)];

        // Explicit grade-appropriate length instruction, per Part 2 of the
        // Activity Content Addition — shorter/simpler for Grade 1, longer for Grade 3.
        return match ($gradeLevel) {
            'Grade 1' => ucfirst($w(0)) . " and " . $w(1) . " are here. Look at the " . $w(2) . ".",
            'Grade 2' => "Today we will learn about $topic. The " . $w(0) . " and the " . $w(1) .
                         " are near the " . $w(2) . ". Can you find the " . $w(3) . "?",
            'Grade 3' => "This week our class is exploring $topic. We noticed that the " . $w(0) .
                         " was close to the " . $w(1) . ", while the " . $w(2) . " stayed near the " .
                         $w(3) . ". Everyone was curious about the " . $w(4) . " as well.",
            default => "Let's learn about $topic today.",
        };
    }

    private static function buildLetterSoundPairs(): array
    {
        $letters = array_keys(self::LETTER_SOUNDS);
        shuffle($letters);
        $chosen = array_slice($letters, 0, 5); // minimum 5

        $pairs = [];
        foreach ($chosen as $letter) {
            $others = array_values(array_diff($letters, [$letter]));
            shuffle($others);
            $distractors = array_slice($others, 0, 2);
            $pairs[] = [
                'letter' => $letter,
                'correctSound' => self::LETTER_SOUNDS[$letter],
                'distractorSounds' => array_map(fn($l) => self::LETTER_SOUNDS[$l], $distractors),
            ];
        }
        return $pairs;
    }

    private static function buildTargetWords(array $wordPool): array
    {
        return array_map(function ($entry) {
            $letters = str_split($entry['word']);
            $shuffled = $letters;
            // Ensure the scramble actually differs from the original for words > 1 char.
            $attempts = 0;
            do {
                shuffle($shuffled);
                $attempts++;
            } while ($shuffled === $letters && count($letters) > 1 && $attempts < 10);

            return [
                'word' => $entry['word'],
                'scrambledLetters' => $shuffled,
                'imageHint' => $entry['imageHint'],
            ];
        }, $wordPool);
    }

    private static function buildScrambledSentences(array $wordPool): array
    {
        $words = array_column($wordPool, 'word');
        $sentences = [];
        $count = min(5, max(3, count($words)));
        for ($i = 0; $i < $count; $i++) {
            $w1 = $words[$i % count($words)];
            $w2 = $words[($i + 1) % count($words)];
            $correct = "The " . $w1 . " is near the " . $w2 . ".";
            $tokens = explode(' ', rtrim($correct, '.'));
            $tokens[] = rtrim(array_pop($tokens), '') . '.'; // keep punctuation on last token, simplified
            $scrambled = $tokens;
            $attempts = 0;
            do {
                shuffle($scrambled);
                $attempts++;
            } while ($scrambled === $tokens && $attempts < 10);

            $sentences[] = ['correct' => $correct, 'scrambled' => $scrambled];
        }
        return $sentences;
    }
}

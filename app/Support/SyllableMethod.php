<?php

namespace App\Support;

/**
 * The syllable method (config/syllable_method.php): segment and blend, taught through repeated drill, inside structured phonics.
 *
 * This reads that one config file and answers the questions the rest of the app asks about it: which words suit a child's step,
 * what the drill looks like for a word, what to tell the activity writer, and (for the mistake patterns) whether a child read
 * a longer word in pieces. It is plain rules over a fixed word list, not a trained model, and it never scores a piece of a
 * word: only the whole word read inside a sentence is scored (see the header of the config file for why).
 */
class SyllableMethod
{
    /** @return list<array{word: string, parts: list<string>, level: int, starter: bool, sentence: string, alone?: bool}> */
    public static function words(?int $level = null): array
    {
        $all = array_values(config('syllable_method.words', []));

        return $level === null ? $all : array_values(array_filter($all, fn (array $w) => $w['level'] === $level));
    }

    /** The bank entry for a word, or null when it is not one of the method's words. */
    public static function find(string $word): ?array
    {
        $word = strtolower(trim($word));

        foreach (config('syllable_method.words', []) as $entry) {
            if ($entry['word'] === $word) {
                return $entry;
            }
        }

        return null;
    }

    public static function isBankWord(string $word): bool
    {
        return self::find($word) !== null;
    }

    /**
     * Which level of word suits a step on the reading path. The level rises as the child's level rises (Beginning, Developing,
     * Proficient). A child on the Letters step has not met words yet, so there is no word level for them: they clap the parts
     * of spoken words, which needs no letters (RL1PA-I-2 is listening and speaking).
     */
    public static function levelForRung(?int $rung): ?int
    {
        if ($rung === null || $rung < 1) {
            return null;
        }

        foreach (config('syllable_method.levels', []) as $level => $def) {
            if (in_array($rung, $def['rungs'], true)) {
                return (int) $level;
            }
        }

        return null;
    }

    /** The words a child at a step can be given: their own level, with the easier levels kept for review. */
    public static function wordsForRung(?int $rung): array
    {
        $level = self::levelForRung($rung);

        return $level === null ? [] : array_values(array_filter(self::words(), fn (array $w) => $w['level'] <= $level));
    }

    /** "sun-set" */
    public static function segmented(array $parts): string
    {
        return implode('-', $parts);
    }

    /** The drill text of the sheet: each part repeated, then the next ("sun sun sun sun set set set set"). */
    public static function drillText(array $parts, ?int $repeat = null): string
    {
        $repeat ??= (int) config('syllable_method.repeat', 4);

        return implode(' ', array_map(fn (string $part) => trim(str_repeat($part.' ', max(1, $repeat))), $parts));
    }

    /**
     * What a teacher does, step by step, with one word. Four lines, in the order of the method.
     *
     * @return list<array{step: int, name: string, text: string, scored: bool}>
     */
    public static function routine(array $entry): array
    {
        [$a, $b] = [$entry['parts'][0], $entry['parts'][1] ?? $entry['parts'][0]];
        $repeat = (int) config('syllable_method.repeat', 4);
        $steps = config('syllable_method.steps');

        $text = [
            1 => "Say each part {$repeat} times together, then let the child say it alone: ".self::drillText($entry['parts'], $repeat).'.',
            2 => 'Say the word, then tap or clap once for each part: '.self::segmented($entry['parts']).'.',
            3 => "Slide the parts together: {$a}, {$b}, ".$entry['word'].'.',
            4 => 'Read the whole word inside a sentence: "'.$entry['sentence'].'"',
        ];

        $out = [];
        foreach ($steps as $n => $step) {
            $out[] = ['step' => (int) $n, 'name' => $step['name'], 'text' => $text[$n], 'scored' => (bool) $step['scored']];
        }

        return $out;
    }

    /**
     * A short instruction for the activity writer (its free text notes field), for word lists at a step. It carries the two
     * corrections as rules: closed syllables first, and no open syllables. Null when the step has no word level.
     */
    public static function writerNote(?int $rung): ?string
    {
        $level = self::levelForRung($rung);
        if ($level === null) {
            return null;
        }

        $examples = array_slice(self::words($level), 0, 2);
        $shown = implode(' and ', array_map(fn ($w) => $w['word'].' ('.self::segmented($w['parts']).')', $examples));
        $shape = config("syllable_method.levels.{$level}.pattern");

        return "Where it fits, use two-syllable words that split cleanly ({$shape}), such as {$shown}. Avoid open syllables like baby.";
    }

    /** The words of the method as sentences a game can offer, grouped by how long they are (3 or 4 words, 5 to 7, 8 or more). */
    public static function sentencesByLength(): array
    {
        $levels = [1 => [], 2 => [], 3 => []];

        foreach (config('syllable_method.words', []) as $entry) {
            $count = str_word_count($entry['sentence']);
            $levels[$count <= 4 ? 1 : ($count <= 7 ? 2 : 3)][] = $entry['sentence'];
        }

        return $levels;
    }

    /**
     * A rough test for "a word of more than one syllable", for words the method does not list: at least five letters and two
     * groups of vowels, not counting a silent final e (cake) but counting a final consonant + le (table). It is used only to
     * order words for a game and to recognise a word read in pieces, never to score a reading.
     */
    public static function looksMultiSyllable(string $word): bool
    {
        $word = strtolower(trim($word));

        if (! preg_match('/^[a-z]{5,}$/', $word)) {
            return false;
        }

        if (self::isBankWord($word)) {
            return true;
        }

        // A silent final e (cake) is not a syllable; a final consonant + le (table) is.
        if (preg_match('/[^aeiouyl]e$/', $word) === 1) {
            $word = substr($word, 0, -1);
        }

        return preg_match_all('/[aeiouy]+/', $word) >= 2;
    }

    /** The parts of a word: from the method's list when it has the word, else null. */
    public static function partsOf(string $word): ?array
    {
        return self::find($word)['parts'] ?? null;
    }

    /**
     * Whether what the child said is only a PART of the longer word on the page: one of the method's syllables, or the start or
     * end of a longer word (sun for sunset, kit for kitten). Used by the mistake patterns to tell "read only part of it" from a
     * different word or a changed ending.
     */
    public static function isPartOf(string $reference, string $spoken): bool
    {
        $reference = strtolower(trim($reference));
        $spoken = strtolower(trim($spoken));

        if ($spoken === '' || $spoken === $reference || ! self::looksMultiSyllable($reference)) {
            return false;
        }

        $parts = self::partsOf($reference);
        if ($parts !== null) {
            foreach ($parts as $part) {
                // The recognizer writes a part that sounds the same as another word the way it spells it (sun heard as son).
                if ($spoken === $part || SpeechNormalizer::sameWord($part, $spoken) !== null) {
                    return true;
                }
            }
        }

        // Not in the list: the start or the end of the word, at least three letters and at least two letters shorter.
        return strlen($spoken) >= 3
            && strlen($spoken) <= strlen($reference) - 2
            && (str_starts_with($reference, $spoken) || str_ends_with($reference, $spoken));
    }

    /**
     * Whether the child read the word in two pieces that the recognizer wrote as two words (sunset heard as "sun" and "set", or
     * basket heard as "bass" and "kit"): the two pieces joined are the word, or within two letters of it. The pieces must be at
     * least two letters each.
     */
    public static function readInPieces(string $reference, string $spoken, string $extra): bool
    {
        $reference = strtolower(trim($reference));
        $spoken = strtolower(trim($spoken));
        $extra = strtolower(trim($extra));

        if (strlen($spoken) < 2 || strlen($extra) < 2 || ! self::looksMultiSyllable($reference)) {
            return false;
        }

        foreach ([$spoken.$extra, $extra.$spoken] as $joined) {
            if ($joined === $reference || (strlen($reference) >= 6 && levenshtein($joined, $reference) <= 2)) {
                return true;
            }
        }

        return false;
    }
}

<?php

namespace App\Support;

/**
 * Fixes what speech recognition gets wrong that is NOT a mistake by the child.
 *
 * The scoring service compares the words it wrote down with the words in the text, letter for
 * letter. That marks a child wrong for things they read correctly:
 *  - a word that sounds exactly like the text's word (sun / son, red / read, to / two): reading
 *    aloud cannot tell them apart, so the recognizer's choice is a coin toss;
 *  - a spelling variant of the same word (mangoes / mangos, mom / mum);
 *  - a number in the text (2) that the recognizer writes as a word (two);
 *  - one word the recognizer split in two (sandcastle written as "sand" and "castle");
 *  - an apostrophe the recognizer left out (dont / don't).
 *
 * Each is turned into a right word (marked accepted, with what was heard kept), and the accuracy is
 * worked out again from the words. The service's own number is kept as accuracy_score_service so
 * nothing is hidden. Only true same-sound words are accepted: a word that is merely close in sound
 * (ripe and right) is a real difference and stays a mistake, and the "not sure" rule handles it
 * when it was heard faintly (see NotSure). The lists are in config/reading_words.php so a teacher
 * can read and correct them.
 *
 * Run once, where the service's answer comes back (ReadingAiClient), so every screen, every saved
 * reading and every alert sees the same corrected words.
 */
class SpeechNormalizer
{
    /**
     * @param  array<string, mixed>  $result  a Reading-api /analyze result
     * @return array<string, mixed>
     */
    public static function apply(array $result): array
    {
        $feedback = $result['accuracy']['word_feedback'] ?? null;

        if (! is_array($feedback) || $feedback === []) {
            return $result;
        }

        $accepted = 0;
        $groups = self::groups();
        $count = count($feedback);

        foreach ($feedback as $i => $entry) {
            if (($entry['status'] ?? null) !== 'substitution') {
                continue;
            }

            $reference = self::word($entry['reference'] ?? '');
            $spoken = self::word($entry['spoken'] ?? '');

            if ($reference === '' || $spoken === '') {
                continue;
            }

            $why = self::sameWord($reference, $spoken, $groups);

            // One word written as two: the extra word is right next to it, before or after.
            if ($why === null) {
                foreach ([$i - 1, $i + 1] as $j) {
                    if ($j < 0 || $j >= $count || ($feedback[$j]['status'] ?? null) !== 'insertion' || ! empty($feedback[$j]['merged'])) {
                        continue;
                    }
                    $extra = self::word($feedback[$j]['spoken'] ?? '');
                    $joined = $j < $i ? $extra.$spoken : $spoken.$extra;
                    if ($extra !== '' && $joined === $reference) {
                        $feedback[$j]['merged'] = true;
                        $why = 'split';
                        break;
                    }
                }
            }

            if ($why !== null) {
                $feedback[$i]['heard'] = $entry['spoken'];
                $feedback[$i]['spoken'] = $entry['reference'];
                $feedback[$i]['status'] = 'correct';
                $feedback[$i]['accepted'] = $why;
                $accepted++;
            }
        }

        if ($accepted === 0) {
            return $result;
        }

        $result['accuracy']['word_feedback'] = $feedback;
        $result['accuracy']['accepted_count'] = $accepted;

        $reference = $correct = 0;
        foreach ($feedback as $entry) {
            if (($entry['status'] ?? 'correct') === 'insertion') {
                continue;
            }
            $reference++;
            $correct += ($entry['status'] ?? 'correct') === 'correct' ? 1 : 0;
        }

        $result['accuracy']['accuracy_score_service'] = $result['accuracy']['accuracy_score'] ?? null;
        $result['accuracy']['substitutions'] = max(0, (int) ($result['accuracy']['substitutions'] ?? 0) - $accepted);
        if ($reference > 0) {
            $result['accuracy']['accuracy_score'] = round($correct / $reference * 100, 2);
        }

        return $result;
    }

    /** Why two different written words are the same word read aloud, or null when they are not. */
    public static function sameWord(string $reference, string $spoken, ?array $groups = null): ?string
    {
        if ($reference === $spoken) {
            return 'same';
        }

        // An apostrophe the recognizer left out.
        if (str_replace("'", '', $reference) === str_replace("'", '', $spoken)) {
            return 'apostrophe';
        }

        $numbers = config('reading_words.numbers', []);
        if ((isset($numbers[$reference]) && $numbers[$reference] === $spoken) || (isset($numbers[$spoken]) && $numbers[$spoken] === $reference)) {
            return 'number';
        }

        $groups ??= self::groups();
        foreach ($groups['homophone'] as $group) {
            if (in_array($reference, $group, true) && in_array($spoken, $group, true)) {
                return 'homophone';
            }
        }
        foreach ($groups['variant'] as $group) {
            if (in_array($reference, $group, true) && in_array($spoken, $group, true)) {
                return 'variant';
            }
        }

        return null;
    }

    /** @return array{homophone: list<list<string>>, variant: list<list<string>>} */
    private static function groups(): array
    {
        $lower = fn (array $set) => array_map(fn (array $g) => array_map('strtolower', $g), $set);

        return [
            'homophone' => $lower(config('reading_words.homophones', [])),
            'variant' => $lower(config('reading_words.variants', [])),
        ];
    }

    private static function word(mixed $word): string
    {
        return strtolower(trim((string) $word));
    }
}

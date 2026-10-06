<?php

namespace App\Support;

use App\Models\Activity;
use Illuminate\Support\Collection;

/**
 * What KIND of mistakes a child makes, not only how many.
 *
 * Every scored reading already stores a word by word record (word_feedback: the word in the text, what
 * was heard, and whether it was right, a different word, or skipped). This turns those records into a
 * small, explainable profile: "skips small words like the and was", "changes vowel sounds (hat as hit)",
 * "leaves off word endings". The profile feeds four things: the alerts a teacher reads (the evidence and
 * the suggested step), which of the teacher's own activities is offered next, which words a game
 * practises, and what a parent can practise at home.
 *
 * It is deliberately rules, not a trained model. The rules are the ones a reading teacher uses to listen
 * to a child (initial and final sounds, vowels, blends, endings, sight words, look-alike letters), each
 * tied to the MATATAG competency it practises, so every suggestion can be explained and defended. A model
 * trained on children's recordings would need a dataset the project does not have yet; this reads the
 * data the project already collects. A pattern is only reported once there is enough evidence (see
 * MIN_READINGS and MIN_MISSES) and says so when it is early.
 */
class ErrorPatterns
{
    /** Readings before a pattern is reported at all, and before it stops being called "early". */
    public const MIN_READINGS = 3;

    public const SETTLED_READINGS = 5;

    /** Missed words needed before a pattern is reported (a handful of mistakes is not a pattern). */
    public const MIN_MISSES = 6;

    /**
     * Each category: how it is said to a teacher and to a parent, one thing to try, and the MATATAG
     * competency it practises (codes and wording checked against the curriculum guides).
     */
    public const CATEGORIES = [
        'small_words' => [
            'label' => 'Small words skipped',
            'teacher' => 'skips small words such as "the", "was" or "said"',
            'parent' => 'skips small everyday words',
            'tip' => 'Practise the missed small words on cards before reading, then read the sentence again.',
            'code' => 'RL1VWK-I-3', 'codeText' => 'Read high-frequency words accurately for meaning.',
        ],
        'endings' => [
            'label' => 'Word endings',
            'teacher' => 'leaves off or changes word endings (plurals, -ed, -ing)',
            'parent' => 'leaves off or changes the ends of words',
            'tip' => 'Point to the last letters of each word while reading, and read a few words with the same ending together.',
            'code' => 'EN2PWS-II-2', 'codeText' => 'Read words accurately and automatically according to word patterns (initial, final, medial).',
        ],
        'vowels' => [
            'label' => 'Vowel sounds',
            'teacher' => 'mixes up the vowel in the middle of a word (hat read as hit)',
            'parent' => 'mixes up the middle sound of words',
            'tip' => 'Change just the middle sound in a short word (hat, hit, hot) and have the child say each one.',
            'code' => 'RL1PWS-I-4', 'codeText' => 'Substitute individual sounds in simple words to make new words.',
        ],
        'beginning' => [
            'label' => 'First sounds',
            'teacher' => 'mixes up the first sound of a word (cat read as hat)',
            'parent' => 'mixes up the first sound of words',
            'tip' => 'Say a word and ask for its first sound, then sort picture or word cards by first sound.',
            'code' => 'RL1PWS-I-3', 'codeText' => 'Isolate sounds (consonants and vowels) in a word (beginning and/or ending).',
        ],
        'ending_sound' => [
            'label' => 'Last sounds',
            'teacher' => 'mixes up the last sound of a word (pig read as pit)',
            'parent' => 'mixes up the last sound of words',
            'tip' => 'Say a word and ask for its last sound, then match words that end the same.',
            'code' => 'RL1PWS-I-3', 'codeText' => 'Isolate sounds (consonants and vowels) in a word (beginning and/or ending).',
        ],
        'blends' => [
            'label' => 'Letter blends',
            'teacher' => 'drops or changes letters in blends such as st, bl or tr (frog read as fog)',
            'parent' => 'finds two-letter sounds like st, bl or tr hard',
            'tip' => 'Slowly blend the two sounds first (s-t, b-l), then add the rest of the word.',
            'code' => 'RL1PWS-I-5', 'codeText' => 'Sound out words accurately.',
        ],
        'look_alike' => [
            'label' => 'Look-alike letters',
            'teacher' => 'mixes up look-alike letters or reads words backwards (b and d, was and saw)',
            'parent' => 'mixes up letters that look alike, such as b and d',
            'tip' => 'Trace the letters, use a pointer finger under each word, and compare b and d side by side.',
            'code' => 'RL1PWS-I-2', 'codeText' => 'Identify the letters in L1.',
        ],
        'skipped' => [
            'label' => 'Words skipped',
            'teacher' => 'skips words while reading',
            'parent' => 'skips words while reading',
            'tip' => 'Read a sentence together in a quiet voice, with the child pointing under each word.',
            'code' => 'RL1CAT-III-1', 'codeText' => 'Read sentences with appropriate speed, accuracy, and expression.',
        ],
        'other' => [
            'label' => 'A different word',
            'teacher' => 'reads a different word from the one on the page',
            'parent' => 'sometimes reads a different word',
            'tip' => 'Sound the missed words out slowly, one sound at a time, before reading them in the sentence.',
            'code' => 'RL1PWS-I-5', 'codeText' => 'Sound out words accurately.',
        ],
    ];

    /**
     * The kind of mistake one entry of a word by word record is, or null when it is not a mistake the
     * child made (a right word, a word the app was not sure about, or an extra word said).
     *
     * @param  array{reference?: ?string, spoken?: ?string, status?: string, unsure?: bool}  $entry
     */
    public static function classify(array $entry): ?string
    {
        $status = $entry['status'] ?? 'correct';
        $reference = self::word($entry['reference'] ?? null);

        if ($reference === '' || $status === 'correct' || $status === 'insertion' || ! empty($entry['unsure'])) {
            return null;
        }

        if ($status === 'deletion') {
            return in_array($reference, config('reading_words.high_frequency', []), true) ? 'small_words' : 'skipped';
        }

        $spoken = self::word($entry['spoken'] ?? null);

        if ($spoken === '' || $spoken === $reference) {
            return 'other';
        }

        // Read backwards (was as saw) or a look-alike letter swapped (bad as dad).
        if (strrev($reference) === $spoken || self::onlyLookAlikeDiffers($reference, $spoken)) {
            return 'look_alike';
        }

        // The ending: the child's word is the text's word with an ending taken off, or one put on.
        if (self::endingDiffers($reference, $spoken)) {
            return 'endings';
        }

        // Same consonants, different vowel (hat and hit).
        if (strlen($reference) === strlen($spoken)
            && preg_replace('/[aeiou]/', '', $reference) === preg_replace('/[aeiou]/', '', $spoken)
            && preg_replace('/[^aeiou]/', '', $reference) !== preg_replace('/[^aeiou]/', '', $spoken)) {
            return 'vowels';
        }

        // A blend in the text that the child's word does not have (frog read as fog).
        if (self::blendLost($reference, $spoken)) {
            return 'blends';
        }

        // Same word apart from its first letter (cat and hat), or apart from its last (pig and pit).
        if ($reference[0] !== $spoken[0] && levenshtein(substr($reference, 1), substr($spoken, 1)) <= 1 && strlen($reference) <= 6) {
            return 'beginning';
        }

        if ($reference[0] === $spoken[0] && substr($reference, -1) !== substr($spoken, -1) && levenshtein($reference, $spoken) <= 2) {
            return 'ending_sound';
        }

        return 'other';
    }

    /**
     * A child's pattern over their recent readings.
     *
     * @param  Collection<int, \App\Models\ReadingSession>  $sessions  practice readings, newest first
     * @return array{
     *   readings: int, misses: int, enough: bool, early: bool,
     *   top: list<array{key: string, label: string, count: int, share: int, examples: list<string>}>,
     *   missedWords: array<string, int>
     * }
     */
    public static function profile(Collection $sessions, int $limit = 8): array
    {
        $recent = $sessions->take($limit);
        $counts = [];
        $examples = [];
        $missedWords = [];
        $misses = 0;

        foreach ($recent as $session) {
            foreach ((array) ($session->word_feedback ?? []) as $entry) {
                $kind = self::classify((array) $entry);
                if ($kind === null) {
                    continue;
                }

                $misses++;
                $counts[$kind] = ($counts[$kind] ?? 0) + 1;

                $ref = self::word($entry['reference'] ?? null);
                $missedWords[$ref] = ($missedWords[$ref] ?? 0) + 1;

                $spoken = self::word($entry['spoken'] ?? null);
                $example = $spoken !== '' && $spoken !== $ref ? "{$ref} as {$spoken}" : $ref;
                $examples[$kind] ??= [];
                if (count($examples[$kind]) < 3 && ! in_array($example, $examples[$kind], true)) {
                    $examples[$kind][] = $example;
                }
            }
        }

        arsort($counts);
        arsort($missedWords);

        $readings = $recent->count();
        $enough = $readings >= self::MIN_READINGS && $misses >= self::MIN_MISSES;

        $top = [];
        foreach (array_slice($counts, 0, 3, true) as $key => $count) {
            $top[] = [
                'key' => $key,
                'label' => self::CATEGORIES[$key]['label'],
                'count' => $count,
                'share' => $misses > 0 ? (int) round($count / $misses * 100) : 0,
                'examples' => $examples[$key] ?? [],
            ];
        }

        return [
            'readings' => $readings,
            'misses' => $misses,
            'enough' => $enough,
            'early' => $enough && $readings < self::SETTLED_READINGS,
            // Only a pattern that covers a real share of the mistakes is worth naming.
            'top' => $enough ? array_values(array_filter($top, fn ($t) => $t['share'] >= 25)) : [],
            'missedWords' => $missedWords,
        ];
    }

    /**
     * One child's profile over their last eight scored practice readings, the same way every screen
     * works it out (the first-check readings are left out: a placement item is not ongoing practice).
     */
    public static function forLearner(\App\Models\Learner $learner): array
    {
        return self::profile(
            \App\Models\ReadingSession::where('learner_id', $learner->id)
                ->where('session_type', 'Practice')
                ->whereNotNull('word_feedback')
                ->orderByDesc('timestamp')
                ->limit(8)
                ->get()
        );
    }

    /** The child's main pattern key, or null when there is not enough evidence. */
    public static function mainPattern(array $profile): ?string
    {
        return $profile['top'][0]['key'] ?? null;
    }

    /**
     * How well an activity practises a pattern, 0 to 1: the share of its words that show the feature
     * of the pattern, together with how many of the child's own missed words it contains. Patterns
     * about a particular sound in the middle of a word have no spelling feature to count, so for
     * those the child's own missed words carry the whole score.
     *
     * @param  array<string, int>  $missedWords
     */
    public static function fit(Activity $activity, ?string $pattern, array $missedWords = []): float
    {
        $words = self::wordsOf($activity);
        if ($words === []) {
            return 0.0;
        }

        $own = count(array_filter($words, fn ($w) => isset($missedWords[$w]))) / count($words);

        $feature = match ($pattern) {
            'small_words' => count(array_filter($words, fn ($w) => in_array($w, config('reading_words.high_frequency', []), true))) / count($words),
            'endings' => count(array_filter($words, fn ($w) => preg_match('/(s|ed|ing|es|ly)$/', $w) === 1 && strlen($w) > 3)) / count($words),
            'blends' => count(array_filter($words, fn ($w) => self::hasBlend($w))) / count($words),
            default => 0.0,
        };

        return round(min(1.0, ($pattern === null || $feature === 0.0) ? min(1.0, $own * 2) : (0.65 * min(1.0, $feature * 1.5) + 0.35 * min(1.0, $own * 2))), 3);
    }

    /** Words of one text, lower case. */
    public static function wordsOf(Activity $activity): array
    {
        preg_match_all("/[a-z]+(?:'[a-z]+)?/", mb_strtolower((string) ($activity->reference_text ?? $activity->passage_text)), $m);

        return $m[0];
    }

    /**
     * Words from a list ordered so those that practise a pattern come first.
     *
     * @param  list<string>  $words
     * @return list<string>
     */
    public static function orderWords(array $words, ?string $pattern): array
    {
        if ($pattern === null) {
            return $words;
        }

        usort($words, fn ($a, $b) => (int) self::practisesPattern($b, $pattern) <=> (int) self::practisesPattern($a, $pattern));

        return $words;
    }

    /**
     * Whether one word shows the spelling feature a pattern is about (a blend, an ending, a small
     * everyday word). The other patterns (a middle sound, a first sound) have no spelling feature to
     * look for, so no word is singled out for them: the child's own missed words carry those.
     */
    public static function practisesPattern(string $word, ?string $pattern): bool
    {
        return match ($pattern) {
            'blends' => self::hasBlend($word),
            'endings' => preg_match('/(s|ed|ing|es)$/', $word) === 1 && strlen($word) > 3,
            'small_words' => in_array($word, config('reading_words.high_frequency', []), true),
            default => false,
        };
    }

    private static function word(?string $word): string
    {
        return strtolower(trim((string) $word));
    }

    private static function hasBlend(string $word): bool
    {
        $start = config('reading_words.start_blends', []);
        $end = config('reading_words.end_blends', []);

        return strlen($word) >= 3 && (in_array(substr($word, 0, 2), $start, true) || in_array(substr($word, -2), $end, true));
    }

    private static function blendLost(string $reference, string $spoken): bool
    {
        $start = config('reading_words.start_blends', []);
        $end = config('reading_words.end_blends', []);

        if (strlen($reference) >= 3 && in_array(substr($reference, 0, 2), $start, true) && substr($spoken, 0, 2) !== substr($reference, 0, 2)) {
            return true;
        }

        return strlen($reference) >= 3 && in_array(substr($reference, -2), $end, true) && substr($spoken, -2) !== substr($reference, -2)
            && levenshtein($reference, $spoken) <= 2;
    }

    private static function endingDiffers(string $reference, string $spoken): bool
    {
        $endings = ['s', 'es', 'ed', 'd', 'ing', 'ly', 'er', 'est'];

        if (str_starts_with($reference, $spoken) && strlen($spoken) >= 2) {
            return in_array(substr($reference, strlen($spoken)), $endings, true);
        }

        if (str_starts_with($spoken, $reference) && strlen($reference) >= 2) {
            return in_array(substr($spoken, strlen($reference)), $endings, true);
        }

        // jumped and jumping share a stem and differ only in the ending.
        foreach (['ing', 'ed', 'es', 's'] as $a) {
            foreach (['ing', 'ed', 'es', 's', ''] as $b) {
                if ($a !== $b && str_ends_with($reference, $a) && str_ends_with($spoken, $b)) {
                    $stem = substr($reference, 0, -strlen($a));
                    if (strlen($stem) >= 3 && $stem === ($b === '' ? $spoken : substr($spoken, 0, -strlen($b)))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private static function onlyLookAlikeDiffers(string $a, string $b): bool
    {
        if (strlen($a) !== strlen($b)) {
            return false;
        }

        $diff = 0;
        for ($i = 0, $n = strlen($a); $i < $n; $i++) {
            if ($a[$i] === $b[$i]) {
                continue;
            }
            $diff++;
            $pair = [$a[$i], $b[$i]];
            sort($pair);
            $ok = false;
            foreach (config('reading_words.look_alike_pairs', []) as $p) {
                $q = $p;
                sort($q);
                if ($q === $pair) {
                    $ok = true;
                }
            }
            if (! $ok) {
                return false;
            }
        }

        return $diff >= 1;
    }
}

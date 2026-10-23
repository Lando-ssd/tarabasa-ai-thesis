<?php

namespace App\Support;

use App\Models\Activity;

/**
 * Scoring for an item where a child names letters aloud (the first reading check's letters step, and a
 * letters row in Word Train).
 *
 * Why letters need their own step. The speech service writes down WORDS. A letter name is one short
 * sound, so what comes back is often not the letter: the recognizer writes "are" or "our" for R, "see"
 * for C, "as" for S, and when letters are said close together it writes them as ONE word ("cb" for C then
 * B, "fl" for F then L). The service then marks the child wrong, although they named every letter.
 * Measured on the live service with synthesized voices (2026-10-23): S A T P I N came back as "as a t p
 * i n" (scored 83 percent), M D O G C B as "m d o g cb" (67 percent).
 *
 * What this does, and nothing more. It keeps the service's own alignment of what was heard against the
 * letters, and repairs only the entries the service marked as a different word or as skipped:
 *  1. a word the service put against a letter is accepted when it is a spelling of that letter's NAME
 *     ("are" for R, "see" for C, config reading_words.letter_names);
 *  2. a word the service put against one letter is accepted for several letters when it is made of the
 *     names of exactly those letters, next to each other and in that order, and the other letters it
 *     stands for were the ones the service had marked as skipped ("cb" for C, B: the service said C was
 *     "cb" and B was skipped; "fl" for F, L: the service said K was "fl" and F, L were skipped).
 * A word that is not a name of the asked letter is still a mistake, a letter that was not asked for
 * never earns anything, nothing is moved to a different place in the row (so a row read in the wrong
 * order gets no credit it did not have), and a letter the service marked right is never touched. So it
 * can only add credit and a reading never scores lower because of it. The service's own number is kept
 * as accuracy_score_service, and every accepted letter keeps what was actually heard (`heard`) so a
 * teacher can see it. Rule based, not a trained model: the table of names is plain text in
 * config/reading_words.php for a teacher to read and correct.
 *
 * PROVISIONAL: the table comes from what the live service wrote for synthesized voices. A child's voice
 * will produce other spellings; they need to be read from real recordings and added.
 */
class LetterNames
{
    /** The most letters one heard word may stand for ("hrefl" is not a word anyone says as one). */
    private const MAX_LUMP = 4;

    /** True for an item where the child names single letters: a 'phonics' item whose every reference word is one letter. */
    public static function appliesTo(Activity $activity): bool
    {
        if ($activity->activity_type !== 'phonics') {
            return false;
        }

        $letters = self::letters((string) ($activity->reference_text ?? $activity->passage_text));

        return $letters !== [] && count(array_filter($letters, fn (string $w) => mb_strlen($w) !== 1)) === 0;
    }

    /**
     * @param  array<string, mixed>  $result  a Reading-api /analyze result (after SpeechNormalizer)
     * @return array<string, mixed>
     */
    public static function apply(array $result, string $reference): array
    {
        $feedback = $result['accuracy']['word_feedback'] ?? null;
        $letters = self::letters($reference);

        if (! is_array($feedback) || $feedback === [] || $letters === []) {
            return $result;
        }

        $entries = array_values($feedback);

        // Where each asked letter sits in the list (an extra word the child said is in the list but is not a letter).
        $at = [];
        foreach ($entries as $k => $entry) {
            if (($entry['status'] ?? 'correct') !== 'insertion') {
                $at[] = $k;
            }
        }

        // Only an item the service lined up against exactly these letters can be repaired.
        if (count($at) !== count($letters)) {
            return $result;
        }
        foreach ($at as $i => $k) {
            if (strtolower(trim((string) ($entries[$k]['reference'] ?? ''))) !== $letters[$i]) {
                return $result;
            }
        }

        $gained = 0;

        // 1. A word the service put against a letter that is a spelling of that letter's name.
        foreach ($at as $i => $k) {
            if (($entries[$k]['status'] ?? null) !== 'substitution') {
                continue;
            }

            $word = self::word($entries[$k]['spoken'] ?? '');

            if ($word !== '' && $word !== $letters[$i] && self::covers($word, [$letters[$i]])) {
                $entries[$k] = ['reference' => $letters[$i], 'spoken' => $letters[$i], 'status' => 'correct', 'heard' => $word, 'accepted' => 'letter-name'];
                $gained++;
            }
        }

        // 2. Several letters written as one word.
        foreach ($at as $i => $k) {
            if (($entries[$k]['status'] ?? null) !== 'substitution') {
                continue;
            }

            $word = self::word($entries[$k]['spoken'] ?? '');
            if ($word === '') {
                continue;
            }

            $span = self::spanFor($word, $i, $letters, $entries, $at);
            if ($span === null) {
                continue;
            }

            [$from, $to] = $span;

            foreach (range($from, $to) as $n => $j) {
                $entries[$at[$j]] = ['reference' => $letters[$j], 'spoken' => $letters[$j], 'status' => 'correct', 'accepted' => 'letters-together']
                    // The first letter keeps the word that was heard; the rest used no spoken word of their own (see NotSure).
                    + ($n === 0 ? ['heard' => $word] : ['covered' => true]);
            }

            // The word was the service's answer for letter $i; if that letter is not one of the span, it is now simply skipped.
            if ($i < $from || $i > $to) {
                $entries[$k] = ['reference' => $letters[$i], 'spoken' => null, 'status' => 'deletion'];
            }

            $gained++;
        }

        if ($gained === 0) {
            return $result;
        }

        $count = fn (string $status) => count(array_filter($entries, fn ($e) => ($e['status'] ?? null) === $status));
        $correct = $count('correct');

        $result['accuracy']['accuracy_score_service'] ??= $result['accuracy']['accuracy_score'] ?? null;
        $result['accuracy']['word_feedback'] = $entries;
        $result['accuracy']['accuracy_score'] = round($correct / count($letters) * 100, 2);
        $result['accuracy']['substitutions'] = $count('substitution');
        $result['accuracy']['deletions'] = $count('deletion');
        $result['accuracy']['insertions'] = $count('insertion');
        $result['accuracy']['accepted_count'] = (int) ($result['accuracy']['accepted_count'] ?? 0) + count(array_filter($entries, fn ($e) => isset($e['accepted'])));
        $result['accuracy']['letters_rescored'] = true;

        return $result;
    }

    /**
     * The letters one heard word stands for, when the service put it against letter $i: a run of 2 to MAX_LUMP letters, next to each
     * other, that the word is made of the names of, which contains $i or touches it, and whose other letters the service had marked
     * as skipped. Returns [first, last] letter positions, or null.
     *
     * @param  list<string>  $letters
     * @param  list<array<string, mixed>>  $entries
     * @param  list<int>  $at
     * @return array{0: int, 1: int}|null
     */
    private static function spanFor(string $word, int $i, array $letters, array $entries, array $at): ?array
    {
        $n = count($letters);

        for ($len = 2; $len <= min(self::MAX_LUMP, $n); $len++) {
            for ($from = max(0, $i - $len); $from <= min($i + 1, $n - $len); $from++) {
                $to = $from + $len - 1;

                // It has to contain the letter the service put the word against, or sit right next to it.
                if ($to < $i - 1 || $from > $i + 1) {
                    continue;
                }

                if (! self::covers($word, array_slice($letters, $from, $len))) {
                    continue;
                }

                // Every other letter of the run must have been skipped, not read as something else.
                $free = true;
                foreach (range($from, $to) as $j) {
                    if ($j !== $i && ($entries[$at[$j]]['status'] ?? null) !== 'deletion') {
                        $free = false;
                        break;
                    }
                }

                if ($free) {
                    return [$from, $to];
                }
            }
        }

        return null;
    }

    /** Whether one written word can be said for exactly these letters, in this order. */
    private static function covers(string $word, array $letters, int $at = 0): bool
    {
        if ($at === count($letters)) {
            return $word === '';
        }

        foreach (self::forms($letters[$at]) as $form) {
            if (str_starts_with($word, $form) && self::covers(substr($word, strlen($form)), $letters, $at + 1)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every way the recognizer writes this letter: the letter itself and the spellings of its name.
     *
     * @return list<string>
     */
    private static function forms(string $letter): array
    {
        $names = config('reading_words.letter_names.'.$letter, []);

        return array_values(array_unique(array_filter(
            array_merge([$letter], array_map('strtolower', $names)),
            fn ($form) => $form !== ''
        )));
    }

    private static function word(mixed $word): string
    {
        return strtolower(trim((string) $word));
    }

    /** @return list<string> */
    private static function letters(string $text): array
    {
        $words = preg_split('/\s+/', strtolower(trim($text))) ?: [];

        return array_values(array_filter($words, fn ($w) => $w !== ''));
    }
}

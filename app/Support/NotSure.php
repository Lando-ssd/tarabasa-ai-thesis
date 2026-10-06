<?php

namespace App\Support;

/**
 * "Not sure": a word the speech service marked as a DIFFERENT word, but heard with very low
 * confidence. A small voice, a noisy room or a short word can make the recognizer guess, and a child
 * should not be marked wrong for something the app did not hear well.
 *
 * Reading-api scores a reading by word and gives each spoken word a confidence in word_timestamps
 * (the same words, in spoken order, that word_feedback was built from, so the Nth spoken word of
 * word_feedback is word_timestamps[N]). A substitution whose spoken word has confidence below the
 * cut-off is marked 'unsure'. An unsure word is left out of the accuracy (it is neither right nor
 * wrong), out of the child's word bank, and out of the alerts. A skipped word (nothing heard at all)
 * is never unsure, and a word the recognizer was confident about is never unsure however wrong it is.
 *
 * PROVISIONAL (config/reading.php): the cut-off and the cap are first guesses, set from synthesized
 * voices. They need a test with real children's recordings before the team calls them final. The cap
 * (a share of the passage) makes sure "not sure" can never hide a reading that is mostly wrong.
 */
class NotSure
{
    /**
     * Returns the Reading-api result with the substitution entries that are not sure marked
     * (`unsure => true`) and the accuracy worked out without them. The raw accuracy is kept as
     * accuracy.accuracy_score_raw and the count as accuracy.not_sure_count.
     *
     * @param  array<string, mixed>  $result  a Reading-api /analyze result
     * @return array<string, mixed>
     */
    public static function apply(array $result): array
    {
        $feedback = $result['accuracy']['word_feedback'] ?? null;

        if (! is_array($feedback) || $feedback === []) {
            return $result;
        }

        $timestamps = $result['word_timestamps'] ?? [];
        $threshold = (float) config('reading.unsure_confidence', 0.5);

        // The reference words (everything but an inserted extra word) and which spoken word each one used.
        $reference = 0;
        $correct = 0;
        $candidates = [];
        $spoken = 0;

        foreach ($feedback as $i => $entry) {
            $status = $entry['status'] ?? 'correct';

            if ($status === 'insertion') {
                $spoken++;

                continue;
            }

            $reference++;

            if ($status === 'deletion') {
                continue;
            }

            if ($status === 'correct') {
                $correct++;
            } elseif ($status === 'substitution') {
                $confidence = $timestamps[$spoken]['confidence'] ?? null;
                if ($confidence !== null && (float) $confidence < $threshold) {
                    $candidates[$i] = (float) $confidence;
                }
            }

            $spoken++;
        }

        // The cap: not sure can cover only a small share of the passage, the least confident words first.
        $cap = (int) floor($reference * (float) config('reading.unsure_max_share', 0.25));
        asort($candidates);
        $unsure = array_slice(array_keys($candidates), 0, max(0, $cap), true);

        if ($unsure === []) {
            return $result;
        }

        foreach ($unsure as $i) {
            $feedback[$i]['unsure'] = true;
        }
        $result['accuracy']['word_feedback'] = $feedback;
        $result['accuracy']['not_sure_count'] = count($unsure);

        $counted = $reference - count($unsure);
        if ($counted > 0) {
            $result['accuracy']['accuracy_score_raw'] = $result['accuracy']['accuracy_score'] ?? null;
            $result['accuracy']['accuracy_score'] = round($correct / $counted * 100, 2);
        }

        return $result;
    }

    /**
     * What a results screen says in numbers: how many words were read right, how many are not sure,
     * and how many there were in all.
     *
     * @return array{right: int, notSure: int, total: int}|null  null when there is no word by word data
     */
    public static function counts(?array $wordFeedback): ?array
    {
        if (empty($wordFeedback)) {
            return null;
        }

        $right = $notSure = $total = 0;

        foreach ($wordFeedback as $entry) {
            $status = $entry['status'] ?? 'correct';

            if ($status === 'insertion') {
                continue;
            }

            $total++;

            if (! empty($entry['unsure'])) {
                $notSure++;
            } elseif ($status === 'correct') {
                $right++;
            }
        }

        return ['right' => $right, 'notSure' => $notSure, 'total' => $total];
    }
}

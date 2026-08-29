<?php

/**
 * SpeechToTextStub — the "documented fallback" your Learner Actor Prompt
 * explicitly allows for ("simulate this ~15% of the time if using the
 * documented fallback"). There is no live Google Cloud Speech-to-Text
 * access in this build/test environment.
 *
 * REAL IMPLEMENTATION SEAM: in production, replace the body of score()
 * with an actual call to Speech-to-Text v2 (chirp_3, en-PH), sending the
 * audio INLINE as base64 in the request (per the Spark-plan decision —
 * no Cloud Storage upload step), then run real word-diff alignment
 * against passageText using the returned transcript + word timings.
 * Nothing outside this class needs to change when that swap happens —
 * the public score() signature is the seam.
 *
 * For testing: passing an audio payload starting with "TESTSIM:" followed
 * by an integer 0-100 deterministically forces that exact accuracy, so the
 * full business-logic chain (thresholds, badges, word bank, notifications)
 * can be tested precisely rather than only against randomness.
 */
class SpeechToTextStub
{
    public static function score(string $audioPayload, string $passageText): array
    {
        // Deterministic test sentinels take priority over the random simulation.
        if ($audioPayload === 'UNCLEARSIM') {
            return ['unclear' => true];
        }
        // ~15% "genuinely couldn't be processed" simulation, per the docs —
        // only applies to non-sentinel (realistic/production-like) input.
        if (!str_starts_with($audioPayload, 'TESTSIM:') && mt_rand(1, 100) <= 15) {
            return ['unclear' => true];
        }

        if (str_starts_with($audioPayload, 'TESTSIM:')) {
            $accuracy = (float) substr($audioPayload, 8);
        } else {
            $accuracy = mt_rand(40, 100);
        }
        $accuracy = max(0, min(100, $accuracy));

        $wordCount = max(1, count(preg_split('/\s+/', trim($passageText))));
        $errorBudget = (int) round($wordCount * (100 - $accuracy) / 100);

        // Distribute the error budget across categories (mock word-diff —
        // real implementation derives this from actual transcript alignment).
        $mispronunciation = (int) round($errorBudget * 0.4);
        $skipped = (int) round($errorBudget * 0.25);
        $substitution = (int) round($errorBudget * 0.2);
        $repetition = (int) round($errorBudget * 0.1);
        $insertion = max(0, $errorBudget - $mispronunciation - $skipped - $substitution - $repetition);

        $pronunciationScore = max(0, min(100, $accuracy + mt_rand(-5, 5)));
        $fluencyScore = max(0, min(100, $accuracy + mt_rand(-8, 8)));

        // Mock WCPM: assume roughly 1 word per 0.5-0.8s at good accuracy, slower when worse.
        $secondsPerWord = 0.5 + (100 - $accuracy) / 100 * 0.6;
        $elapsedMinutes = ($wordCount * $secondsPerWord) / 60;
        $correctWords = $wordCount - $errorBudget;
        $wcpm = $elapsedMinutes > 0 ? round($correctWords / $elapsedMinutes) : $correctWords;

        // Mock "missed words" for PersonalWordBank — real implementation pulls
        // these from the actual diff alignment's substitution/mispronunciation list.
        $words = preg_split('/\s+/', preg_replace('/[^\w\s]/', '', trim($passageText)));
        $missedWords = array_slice(array_unique(array_map('strtolower', $words)), 0, min(2, count($words)));

        return [
            'unclear' => false,
            'accuracy_percent' => $accuracy,
            'wcpm' => $wcpm,
            'pronunciation_score' => $pronunciationScore,
            'fluency_score' => $fluencyScore,
            'mispronunciation_count' => $mispronunciation,
            'skipped_word_count' => $skipped,
            'substitution_count' => $substitution,
            'repetition_count' => $repetition,
            'insertion_count' => $insertion,
            'missed_words' => $missedWords,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\PersonalWordBank;
use App\Models\ReadingSession;
use App\Support\ErrorPatterns;

/**
 * A child's own word bank, kept up to date after every scored reading: one row per word, so what is
 * counted is words and not repeats.
 *
 *  - A word the child misses goes in as Struggling (or goes back to Struggling if they had improved).
 *  - A word already in the bank that they now read correctly counts as one more success: Improving
 *    after the first, Mastered after three, so a word is practised again soon after a miss, then
 *    less and less (spaced review) instead of forever.
 *  - A word the app was only "not sure" about is never added (see App\Support\NotSure), and a word
 *    that is not in the bank is not added just for being right.
 *
 * Games and the activity picker read this bank, so what a child practises is what they actually miss.
 */
class WordBank
{
    /** Correct readings of a bank word before it counts as Mastered. */
    public const MASTERED_AFTER = 3;

    /** The most new words one reading adds, so one hard passage cannot flood the bank. */
    public const MAX_NEW_PER_READING = 4;

    /**
     * @param  array<int, array<string, mixed>>  $feedback  the reading's word_feedback
     */
    public function record(Learner $learner, ReadingSession $session, array $feedback): void
    {
        $added = 0;
        $seen = [];

        foreach ($feedback as $entry) {
            $word = strtolower(trim((string) ($entry['reference'] ?? '')));
            if ($word === '' || isset($seen[$word]) || ! preg_match("/^[a-z']{1,40}$/", $word)) {
                continue;
            }

            $row = PersonalWordBank::where('learner_id', $learner->id)->where('word', $word)->first();

            if (ErrorPatterns::classify((array) $entry) !== null) {
                $seen[$word] = true;

                if ($row === null && $added >= self::MAX_NEW_PER_READING) {
                    continue;
                }

                // A miss: new, or back to Struggling.
                PersonalWordBank::updateOrCreate(
                    ['learner_id' => $learner->id, 'word' => $word],
                    ['session_id' => $session->id, 'mastery_status' => 'Struggling', 'times_drilled' => 0, 'last_reviewed' => now()]
                );
                $added += $row === null ? 1 : 0;

                continue;
            }

            if (($entry['status'] ?? 'correct') === 'correct' && empty($entry['unsure']) && $row !== null && $row->mastery_status !== 'Mastered') {
                $seen[$word] = true;
                $successes = $row->times_drilled + 1;

                $row->update([
                    'times_drilled' => $successes,
                    'mastery_status' => $successes >= self::MASTERED_AFTER ? 'Mastered' : 'Improving',
                    'last_reviewed' => now(),
                ]);
            }
        }
    }

    /**
     * The words a game should practise first for this child: Struggling words, then Improving ones
     * that are due another look, each kept to the level's length.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public function practiceWords(Learner $learner): \Illuminate\Support\Collection
    {
        return PersonalWordBank::where('learner_id', $learner->id)
            ->whereIn('mastery_status', ['Struggling', 'Improving'])
            ->get()
            ->sortBy(fn ($r) => [$r->mastery_status === 'Struggling' ? 0 : 1, $r->last_reviewed?->timestamp ?? 0])
            ->pluck('word')
            ->map(fn ($w) => strtolower(trim($w)))
            ->unique()
            ->values();
    }
}

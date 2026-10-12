<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\GamePlay;
use App\Models\Learner;
use App\Support\ErrorPatterns;
use App\Support\NotSure;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Practice Games: the rules the website's GameController and the phone app's API both use, in one place so the two can
 * never disagree about what a level is, which words a game offers, whether a spoken word was heard, or how a finished game
 * is reported. Practice Games are free play: no points, no streak, no level, and nothing a child says is kept. The one
 * thing a finished session reports is a game_plays row, so the ten Games badges can be earned.
 */
class PracticeGameService
{
    /**
     * Word Builder's 3 levels are length based: 3 letter words, 4 letter words, 5 or 6 letter words. These are the lists the
     * game shipped with (grade 1, 2 and 3's lists were already uniform 3, 4 and 5-6 letter sets), re-framed as levels so a
     * child is not locked to their grade's word length when their real performance says otherwise.
     */
    public const WORD_LEVEL_LISTS = [
        1 => ['cat', 'dog', 'sun', 'hat', 'pig', 'bed', 'cup', 'run', 'big', 'red', 'hen', 'bus', 'box', 'fox', 'mud', 'six', 'wet', 'fan', 'jam', 'log'],
        2 => ['frog', 'star', 'milk', 'nest', 'lamp', 'swim', 'gift', 'sock', 'drum', 'fish', 'wind', 'hand', 'jump', 'camp', 'desk', 'flag', 'spin', 'tent', 'stop', 'pond'],
        3 => ['apple', 'table', 'happy', 'forest', 'purple', 'animal', 'garden', 'yellow', 'wonder', 'basket', 'monkey', 'sudden', 'castle', 'jungle', 'pencil', 'rocket', 'magnet', 'dragon', 'planet', 'silver'],
    ];

    /**
     * Letter Match's 3 levels are pair-count based. Each level is a list of one or more sets: levels 1 and 2 are a single set
     * each, level 3 is the full alphabet in 3 sets of 8 or 9 pairs, played straight through as one attempt (one 52 card grid
     * would be unplayable on a phone).
     */
    public const LETTER_LEVELS = [
        1 => [['A', 'B', 'C', 'D', 'E', 'F']],
        2 => [['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']],
        3 => [
            ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I'],
            ['J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R'],
            ['S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'],
        ],
    ];

    /** The level a game starts a child at: their grade (1 to 3). A child can climb from there in one sitting, never fall back. */
    public function startLevel(Learner $learner): int
    {
        return max(1, min(3, (int) substr((string) $learner->grade_level, 6)));
    }

    /**
     * The words a game can use at each of the three levels: the child's own Struggling words (from their real readings)
     * bucketed by length, the level's fixed word list, and the words from that list that practise the child's main kind of
     * mistake (see ErrorPatterns), offered first when a round needs filling up.
     *
     * @return array<int, array{struggling: list<string>, fallback: list<string>, favoured: list<string>}>
     */
    public function wordLevels(Learner $learner): array
    {
        // Words the child missed first, then ones they are improving on that are due another look.
        $strugglingWords = app(WordBank::class)->practiceWords($learner);
        $pattern = ErrorPatterns::mainPattern(ErrorPatterns::forLearner($learner));

        $levels = [];
        foreach ([1, 2, 3] as $level) {
            $levels[$level] = [
                'struggling' => $strugglingWords
                    ->filter(fn ($word) => $this->wordLengthMatchesLevel(strlen($word), $level))
                    ->values()
                    ->all(),
                'fallback' => self::WORD_LEVEL_LISTS[$level],
                'favoured' => array_values(array_filter(
                    self::WORD_LEVEL_LISTS[$level],
                    fn ($word) => ErrorPatterns::practisesPattern($word, $pattern)
                )),
            ];
        }

        return $levels;
    }

    private function wordLengthMatchesLevel(int $length, int $level): bool
    {
        return match ($level) {
            1 => $length === 3,
            2 => $length === 4,
            default => $length >= 5,
        };
    }

    /**
     * One word the child said into a game, checked by the same service that scores readings. Practice: nothing is saved (no
     * reading session, no word bank, no points) and what the child said is not kept. The answer is only ever "heard" or
     * "again", never "wrong": speech recognition is weaker for young children and for single words, so a game must not tell
     * a child they are wrong when the machine may be. If the service cannot be reached the answer is "unavailable" and the
     * game simply carries on without the speaking part.
     *
     * @return 'heard'|'again'|'unavailable'
     */
    public function checkWord(Learner $learner, string $word, UploadedFile $audio, ReadingAiClient $readingAi): string
    {
        $grade = max(1, min(3, (int) substr((string) $learner->grade_level, 6)));

        // A throwaway, unsaved activity that only tells the service what it is listening to: one word, read aloud, at the
        // child's own grade.
        $activity = new Activity([
            'grade_level' => 'Grade '.$grade,
            'competency' => 'foundational_reading',
            'activity_type' => 'word_reading',
            'difficulty_tier' => 'Easy',
            'curriculum_code' => [1 => 'RL1PWS-I-5', 2 => 'EN2PWS-I-3', 3 => 'EN3PWS-I-2'][$grade],
            'reference_text' => $word,
            'passage_text' => $word,
        ]);
        $activity->id = 0;

        try {
            $outcome = $readingAi->analyze($audio, $activity);
        } catch (ValidationException) {
            return 'unavailable';
        }

        if ($outcome['unclear']) {
            return 'again';
        }

        $feedback = NotSure::apply($outcome['result'])['accuracy']['word_feedback'] ?? [];
        $heard = collect($feedback)->contains(
            fn (array $w) => ($w['reference'] ?? null) === $word && ($w['status'] ?? null) === 'correct'
        );

        return $heard ? 'heard' : 'again';
    }

    /**
     * A game session ended. Records a game_plays row and returns the badges it just earned, for the "All done" screen. The
     * numbers come from the device, but all they can ever earn is a badge: no points, streak or level reads this table, so
     * there is nothing to gain by faking them.
     *
     * @param  array{level_reached: int, top_level_cleared: bool, had_perfect_round: bool, rounds: int}  $data
     * @return list<array{code: string, name: string, icon: string}>
     */
    public function finish(Learner $learner, string $game, array $data): array
    {
        abort_unless(in_array($game, GamePlay::GAMES, true), 404);

        GamePlay::create($data + ['learner_id' => $learner->id, 'game' => $game, 'played_at' => now()]);

        return collect(app(BadgeService::class)->sync($learner->fresh()))
            ->map(fn (array $badge) => [
                'code' => $badge['code'],
                'name' => $badge['name'],
                'icon' => config('badge_icons.icons.'.$badge['code'], config('badge_icons.fallback')),
            ])
            ->values()
            ->all();
    }
}

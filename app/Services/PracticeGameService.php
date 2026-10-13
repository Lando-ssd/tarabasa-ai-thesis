<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\GamePlay;
use App\Models\Learner;
use App\Support\ErrorPatterns;
use App\Support\NotSure;
use App\Support\ReadingLevel;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Practice Games: the rules the website's GameController and the phone app's API both use, in one place so the two can
 * never disagree about what a level is, what a game offers, whether something the child read aloud was heard, or how a
 * finished game is reported.
 *
 * Every game is a reading game: the child reads something out loud (Balloon Pop a word, Word Train a row of words or letters,
 * Sentence Stars a sentence) and the same speech checker that scores readings listens. Practice Games are free play: no
 * points, no streak, no level, and nothing a child says is kept. The one thing a finished session reports is a game_plays
 * row, so the ten Games badges can be earned.
 *
 * They adapt to the child in two ways, both from data the app already has: the level a game STARTS at comes from the child's
 * step on the reading path, and what a game OFFERS comes first from the words the child has been missing and the kind of
 * mistake they make most (see ErrorPatterns), then from the level's own lists.
 */
class PracticeGameService
{
    /** The three levels of Balloon Pop and Word Train's word rows: 3 letter words, 4 letter words, 5 or 6 letter words. */
    public const WORD_LEVEL_LISTS = [
        1 => ['cat', 'dog', 'sun', 'hat', 'pig', 'bed', 'cup', 'run', 'big', 'red', 'hen', 'bus', 'box', 'fox', 'mud', 'six', 'wet', 'fan', 'jam', 'log'],
        2 => ['frog', 'star', 'milk', 'nest', 'lamp', 'swim', 'gift', 'sock', 'drum', 'fish', 'wind', 'hand', 'jump', 'camp', 'desk', 'flag', 'spin', 'tent', 'stop', 'pond'],
        3 => ['apple', 'table', 'happy', 'forest', 'purple', 'animal', 'garden', 'yellow', 'wonder', 'basket', 'monkey', 'sudden', 'castle', 'jungle', 'pencil', 'rocket', 'magnet', 'dragon', 'planet', 'silver'],
    ];

    /** How many cars Word Train has at each level: words for most children, letters for a child still on the letters step. */
    public const TRAIN_CARS = [
        'words' => [1 => 3, 2 => 4, 3 => 5],
        'letters' => [1 => 4, 2 => 5, 3 => 6],
    ];

    /** The share of what was asked that has to be heard for a row or a sentence to count as read (a little under three in four). */
    private const HEARD_SHARE = 0.66;

    /**
     * The level a game starts a child at, from where they are on the reading path (the step set by the first reading check and
     * later readings, or the parent's description before the check): letters and first words start at level 1, short sentences
     * and longer ones at level 2, stories at level 3. A child can climb from there in one sitting, never fall back.
     */
    public function startLevel(Learner $learner): int
    {
        $rung = ReadingLevel::rung($learner);

        if ($rung === null) {
            // No step known yet: the grade is the next best guess.
            return max(1, min(3, (int) substr((string) $learner->grade_level, 6)));
        }

        return match (true) {
            $rung <= 1 => 1,
            $rung <= 3 => 2,
            default => 3,
        };
    }

    /**
     * The words Balloon Pop and Word Train can use at each of the three levels: the child's own Struggling words (from their
     * real readings) bucketed by length, the level's fixed word list, and the words from that list that practise the child's
     * main kind of mistake (see ErrorPatterns), offered first when a round needs filling up.
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
     * What Word Train offers. A child who is still on the letters step gets rows of letters (the same six-letter sets the first
     * reading check uses, so their names are what the speech checker can recognise); everyone else gets rows of words from the
     * same pools Balloon Pop uses.
     *
     * @return array{mode: 'letters'|'words', cars: array<int,int>, levels: array<int, array{struggling: list<string>, fallback: list<string>, favoured: list<string>}>}
     */
    public function trainLevels(Learner $learner): array
    {
        if (ReadingLevel::rung($learner) !== 0) {
            return ['mode' => 'words', 'cars' => self::TRAIN_CARS['words'], 'levels' => $this->wordLevels($learner)];
        }

        $sets = config('diagnostic.letters.sets');
        $pool = [];
        $levels = [];
        foreach ([1, 2, 3] as $level) {
            // Each level adds one more set, so level 3 draws on all eighteen letters.
            $pool = array_merge($pool, array_map('strtolower', $sets[$level - 1]['letters']));
            $levels[$level] = ['struggling' => [], 'favoured' => [], 'fallback' => $pool];
        }

        return ['mode' => 'letters', 'cars' => self::TRAIN_CARS['letters'], 'levels' => $levels];
    }

    /**
     * What Sentence Stars offers at each level: the level's sentences, and "preferred" first the ones that contain a word the
     * child has been missing or that practise their main kind of mistake (when there is one), so the game works on what that
     * child needs. A game never offers a sentence the level does not have.
     *
     * @return array<int, array{preferred: list<string>, fallback: list<string>}>
     */
    public function sentenceLevels(Learner $learner): array
    {
        $practice = app(WordBank::class)->practiceWords($learner)->map(fn ($w) => strtolower((string) $w))->all();
        $pattern = ErrorPatterns::mainPattern(ErrorPatterns::forLearner($learner));

        $levels = [];
        foreach ([1, 2, 3] as $level) {
            $all = array_values(config('practice_sentences.levels.'.$level, []));

            $levels[$level] = [
                'preferred' => array_values(array_filter($all, function (string $sentence) use ($practice, $pattern) {
                    foreach (self::wordsOf($sentence) as $word) {
                        if (in_array($word, $practice, true) || ErrorPatterns::practisesPattern($word, $pattern)) {
                            return true;
                        }
                    }

                    return false;
                })),
                'fallback' => $all,
            ];
        }

        return $levels;
    }

    /** The lowercase words of a sentence, with its punctuation taken off: what the speech checker is asked to listen for. */
    public static function wordsOf(string $text): array
    {
        $clean = preg_replace("/[^a-z' ]+/", ' ', strtolower($text));

        return array_values(array_filter(preg_split('/\s+/', (string) $clean) ?: [], fn ($w) => $w !== ''));
    }

    /**
     * Something the child read aloud into a game, checked by the same service that scores readings. Practice: nothing is saved
     * (no reading session, no word bank, no points) and what the child said is not kept.
     *
     * The answer is only ever "heard" or "again", never "wrong": speech recognition is weaker for young children, so a game must
     * not tell a child they are wrong when the machine may be. `matched` says, for each word asked, whether it was heard, so a
     * game can show the words that went well and ask for the rest again. A word the checker was unsure of is neither counted for
     * nor against. If the service cannot be reached the answer is "unavailable" and the game carries on without the speaking part.
     *
     * @param  list<string>  $words  what was asked, in order, lowercase
     * @param  'word'|'row'|'letters'|'sentence'  $kind
     * @return array{status: 'heard'|'again'|'unavailable', matched: list<bool>}
     */
    public function checkText(Learner $learner, array $words, string $kind, UploadedFile $audio, ReadingAiClient $readingAi): array
    {
        $none = array_fill(0, count($words), false);
        $grade = max(1, min(3, (int) substr((string) $learner->grade_level, 6)));

        // A throwaway, unsaved activity that only tells the service what it is listening to. Letters, rows of words and sentences
        // are graded as the first reading check grades them (their own content's grade, not the child's); a single word is one
        // word at the child's own grade, as before.
        [$type, $competency, $code, $level] = match ($kind) {
            'letters' => ['phonics', 'foundational_reading', 'EN2PWS-I-2', 1],
            'row' => ['phonics_reading', 'foundational_reading', 'RL1PWS-I-5', 1],
            'sentence' => ['sentence_reading', 'reading_fluency', 'RL1CAT-III-1', 1],
            default => ['word_reading', 'foundational_reading', [1 => 'RL1PWS-I-5', 2 => 'EN2PWS-I-3', 3 => 'EN3PWS-I-2'][$grade], $grade],
        };

        $activity = new Activity([
            'grade_level' => 'Grade '.$level,
            'competency' => $competency,
            'activity_type' => $type,
            'difficulty_tier' => 'Easy',
            'curriculum_code' => $code,
            'reference_text' => implode(' ', $words),
            'passage_text' => implode(' ', $words),
        ]);
        $activity->id = 0;

        try {
            $outcome = $readingAi->analyze($audio, $activity);
        } catch (ValidationException) {
            return ['status' => 'unavailable', 'matched' => $none];
        }

        if ($outcome['unclear']) {
            return ['status' => 'again', 'matched' => $none];
        }

        $feedback = NotSure::apply($outcome['result'])['accuracy']['word_feedback'] ?? [];

        // The words asked, in order, are every entry that is not an extra word the child said.
        $matched = [];
        $unsure = 0;
        foreach ($feedback as $entry) {
            if (($entry['status'] ?? null) === 'insertion') {
                continue;
            }
            $matched[] = ($entry['status'] ?? null) === 'correct';
            if (! empty($entry['unsure'])) {
                $unsure++;
            }
        }
        $matched = array_slice(array_pad($matched, count($words), false), 0, count($words));

        $right = count(array_filter($matched));
        $needed = max(1, (int) ceil(self::HEARD_SHARE * max(1, count($words) - $unsure)));

        return ['status' => $right >= $needed ? 'heard' : 'again', 'matched' => $matched];
    }

    /**
     * One word the child said into a game (Balloon Pop). The same check as checkText, as a plain "heard", "again" or "unavailable".
     *
     * @return 'heard'|'again'|'unavailable'
     */
    public function checkWord(Learner $learner, string $word, UploadedFile $audio, ReadingAiClient $readingAi): string
    {
        return $this->checkText($learner, [$word], 'word', $audio, $readingAi)['status'];
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

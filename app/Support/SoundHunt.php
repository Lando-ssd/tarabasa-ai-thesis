<?php

namespace App\Support;

/**
 * Sound Hunt (config/sound_hunt.php): the questions of the listen and tap game, and everything about them that has to be the same on the
 * website and on the phone (which level a child starts at, whether the cards show the written word, what Tara says, which voice clips exist).
 *
 * A question is "which of these three words matches this word?": the answer rhymes with the target (level 1), starts like it (level 2) or ends
 * like it (level 3). The other two cards are words that do NOT match, chosen so the question has one clear answer:
 *   level 1  they end differently and also start differently from the target, so a child cannot answer by the first sound alone;
 *   level 2  they start differently from the target and from each other, and do not rhyme with it, so a rhyme is not a trap;
 *   level 3  they end differently from the target and from each other.
 * Every word is a whole word: a voice can say "sun" but not the single sound /s/ (see config/sound_hunt.php).
 */
class SoundHunt
{
    /** The question kinds, by level. */
    public static function kind(int $level): string
    {
        return (string) config("sound_hunt.levels.$level.kind", 'rhyme');
    }

    /** @return array<int, array{kind: string, name: string, ask: string, code: string, codeText: string}> */
    public static function levels(): array
    {
        return config('sound_hunt.levels');
    }

    /**
     * The level a child starts at: from their step on the reading path (the Letters step starts at rhymes, the first words step at first
     * sounds, higher steps at last sounds), unless their usual mistake points at one kind of question (first sounds, last sounds, or the
     * middle sound, which rhymes work on). A child can climb from there in one sitting and never falls back, as in the other games.
     */
    public static function startLevel(?int $rung, ?string $pattern = null): int
    {
        $byPattern = ['beginning' => 2, 'ending_sound' => 3, 'vowels' => 1][(string) $pattern] ?? null;
        if ($byPattern !== null) {
            return $byPattern;
        }

        return match (true) {
            $rung === null, $rung <= 0 => 1,
            $rung === 1 => 2,
            default => 3,
        };
    }

    /** Whether the cards show the written word: a child on the Letters step (or with no step yet) only hears them. */
    public static function readsWords(?int $rung): bool
    {
        return $rung !== null && $rung >= 1;
    }

    /** Whether the Games page tells this child Sound Hunt suits them: the Letters step (or no step yet) and the first words step. */
    public static function goodForYou(?int $rung): bool
    {
        return ! self::readsWords($rung) || $rung === 1;
    }

    /**
     * The questions of one level, as many as config('sound_hunt.pool'), none with the same target twice. Words the child has missed in their
     * readings (`own`, lowercase) are used as targets first when the level has a group for them.
     *
     * @param  list<string>  $own
     * @return list<array<string, mixed>>
     */
    public static function questions(int $level, array $own = [], ?int $count = null): array
    {
        $count ??= (int) config('sound_hunt.pool');
        $kind = self::kind($level);
        $groups = self::groups($kind);

        // A word can be a target only when its group has another word to be the answer.
        $targets = [];
        foreach ($groups as $key => $words) {
            if (count($words) >= 2) {
                foreach ($words as $word) {
                    $targets[$word] = $key;
                }
            }
        }

        $order = array_values(array_unique(array_merge(
            array_values(array_filter(array_map('strtolower', $own), fn ($w) => isset($targets[$w]))),
            self::shuffled(array_keys($targets))
        )));

        $out = [];
        $used = [];
        foreach ($order as $target) {
            if (count($out) >= $count) {
                break;
            }
            if (isset($used[$target])) {
                continue;
            }
            $question = self::build($kind, $target, $targets[$target], $groups);
            if ($question !== null) {
                $used[$target] = true;
                $out[] = $question;
            }
        }

        return $out;
    }

    /** One question for `$target`, or null when no clear set of cards can be made for it. */
    private static function build(string $kind, string $target, string $groupKey, array $groups): ?array
    {
        $answers = array_values(array_diff($groups[$groupKey], [$target]));
        shuffle($answers);
        $answer = $answers[0] ?? null;
        if ($answer === null) {
            return null;
        }

        $others = self::distractors($kind, $target, $answer, $groupKey, $groups);
        if (count($others) < (int) config('sound_hunt.choices') - 1) {
            return null;
        }

        $choices = self::shuffled(array_merge([$answer], array_slice($others, 0, (int) config('sound_hunt.choices') - 1)));
        $mark = $kind === 'rhyme' ? strlen($groupKey) : 1;

        return [
            'kind' => $kind,
            'target' => $target,
            'answer' => $answer,
            'choices' => $choices,
            'mark' => $mark,
            'say' => self::say($kind, $target, $answer),
            'screen' => self::screen($kind, $target, $answer, $groupKey),
        ];
    }

    /**
     * The words that are NOT a match, one from each of different groups where there are enough, in a random order.
     *
     * @return list<string>
     */
    private static function distractors(string $kind, string $target, string $answer, string $groupKey, array $groups): array
    {
        $rhymeOf = self::rhymeFamilies();
        $picked = [];
        $usedGroups = [$groupKey => true];

        $keys = array_keys($groups);
        shuffle($keys);

        foreach ($keys as $key) {
            if (isset($usedGroups[$key]) || count($picked) >= (int) config('sound_hunt.choices') - 1) {
                continue;
            }
            $words = self::shuffled($groups[$key]);
            foreach ($words as $word) {
                if ($word === $target || $word === $answer) {
                    continue;
                }
                $ok = match ($kind) {
                    // Level 1: a different ending (it is in another family) and a different start, from the target and from the answer.
                    'rhyme' => ($word[0] !== $target[0]) && ($word[0] !== $answer[0]),
                    // Level 2: a different start (another group), and not a rhyme of the target.
                    'first' => ! (isset($rhymeOf[$word], $rhymeOf[$target]) && $rhymeOf[$word] === $rhymeOf[$target]),
                    // Level 3: a different ending (another group).
                    default => true,
                };
                if ($ok) {
                    $picked[] = $word;
                    $usedGroups[$key] = true;
                    break;
                }
            }
        }

        return $picked;
    }

    /** The word groups of a kind: rhyme families, words by first letter, words by last letter. @return array<string, list<string>> */
    public static function groups(string $kind): array
    {
        return config(['rhyme' => 'sound_hunt.rhymes', 'first' => 'sound_hunt.first', 'last' => 'sound_hunt.last'][$kind]);
    }

    /** word => its rhyme family, for every word that is in one. @return array<string, string> */
    private static function rhymeFamilies(): array
    {
        $map = [];
        foreach (config('sound_hunt.rhymes') as $family => $words) {
            foreach ($words as $word) {
                $map[$word] = $family;
            }
        }

        return $map;
    }

    /** @param list<string> $list @return list<string> */
    private static function shuffled(array $list): array
    {
        shuffle($list);

        return array_values($list);
    }

    // ------------------------------------------------------------------------------------------------------------------------- what Tara says

    private static function cap(string $text): string
    {
        return ucfirst($text);
    }

    private static function wordKey(string $word): string
    {
        return 'w-'.$word;
    }

    /**
     * The four things Tara says in a turn, each as the clips that make it (keys, see clipKeys()) and the words to read with the device voice when
     * a clip is missing. A line is played from clips only when every clip it needs exists, so one line never mixes two voices.
     *
     * @return array<string, array{parts: list<string>, text: string}>
     */
    private static function say(string $kind, string $target, string $answer): array
    {
        $ask = ['rhyme' => 'ask-rhyme', 'first' => 'ask-first', 'last' => 'ask-last'][$kind];
        $askText = config('sound_hunt.phrases.'.$ask)." $target?";
        $join = ['rhyme' => ['rhyme', ' rhyme.'], 'first' => ['start-same', ' both start the same.'], 'last' => ['end-same', ' both end the same.']][$kind];
        $reveal = ['rhyme' => ['rhymes-with', 'rhymes with'], 'first' => ['starts-like', 'starts like'], 'last' => ['ends-like', 'ends like']][$kind];

        return [
            'ask' => ['parts' => [$ask, self::wordKey($target)], 'text' => $askText],
            'again' => ['parts' => ['again', $ask, self::wordKey($target)], 'text' => config('sound_hunt.phrases.again').' '.$askText],
            'right' => [
                'parts' => ['yes', self::wordKey($answer), 'and', self::wordKey($target), $join[0]],
                'text' => config('sound_hunt.phrases.yes').' '.self::cap($answer)." and $target".$join[1],
            ],
            'reveal' => [
                'parts' => [self::wordKey($answer), $reveal[0], self::wordKey($target), 'next-one'],
                'text' => self::cap($answer).' '.$reveal[1]." $target. ".config('sound_hunt.phrases.next-one'),
            ],
        ];
    }

    /** What the screen writes after an answer (shorter than what Tara says where the letter helps a child who reads). */
    private static function screen(string $kind, string $target, string $answer, string $groupKey): array
    {
        $right = match ($kind) {
            'rhyme' => 'Yes! '.self::cap($answer)." and $target rhyme.",
            'first' => 'Yes! '.self::cap($answer)." and $target both start with {$groupKey}.",
            default => 'Yes! '.self::cap($answer)." and $target both end with {$groupKey}.",
        };
        $reveal = match ($kind) {
            'rhyme' => self::cap($answer)." rhymes with $target.",
            'first' => self::cap($answer)." starts like $target.",
            default => self::cap($answer)." ends like $target.",
        };

        return ['right' => $right, 'again' => config('sound_hunt.phrases.again'), 'reveal' => $reveal.' '.config('sound_hunt.phrases.next-one')];
    }

    // ------------------------------------------------------------------------------------------------------------------------- the voice clips

    /** Every word the game can say, once. @return list<string> */
    public static function words(): array
    {
        $all = [];
        foreach (['rhymes', 'first', 'last'] as $set) {
            foreach (config("sound_hunt.$set") as $words) {
                foreach ($words as $word) {
                    $all[$word] = true;
                }
            }
        }
        $words = array_keys($all);
        sort($words);

        return $words;
    }

    /** The key and text of every clip the game would like to play: the phrases and every word. @return array<string, string> */
    public static function clipKeys(): array
    {
        $keys = config('sound_hunt.phrases');
        foreach (self::words() as $word) {
            $keys[self::wordKey($word)] = $word;
        }

        return $keys;
    }

    /** The clips that really exist on disk, key => address. Empty until the clips have been made (tools/make-voice-clips.py). @return array<string, string> */
    public static function clips(): array
    {
        $dir = public_path((string) config('sound_hunt.clips.directory'));
        $ext = (string) config('sound_hunt.clips.extension', 'mp3');
        if (! is_dir($dir)) {
            return [];
        }

        $found = [];
        foreach (array_keys(self::clipKeys()) as $key) {
            if (is_file("$dir/$key.$ext")) {
                $found[$key] = asset(config('sound_hunt.clips.directory')."/$key.$ext");
            }
        }

        return $found;
    }
}

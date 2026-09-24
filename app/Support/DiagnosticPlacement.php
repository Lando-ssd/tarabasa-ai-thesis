<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Learner;

/**
 * Where the first-login reading check starts, and how its result is turned
 * into a starting score. The rules and numbers live in config/diagnostic.php;
 * this class only applies them.
 *
 * The ladder, lowest to highest: letters (Grade 1 only), easy, medium, hard.
 */
class DiagnosticPlacement
{
    public const LETTERS = 'letters';

    private const SCALE = ['letters', 'easy', 'medium', 'hard'];

    /** Grade 1 is the only grade whose curriculum has a letters competency. */
    public static function hasLettersRung(Learner $learner): bool
    {
        return $learner->grade_level === 'Grade 1';
    }

    /**
     * @return list<string> The rungs this child can be tested on, lowest first.
     */
    public static function rungsFor(Learner $learner): array
    {
        return self::hasLettersRung($learner) ? self::SCALE : array_slice(self::SCALE, 1);
    }

    /**
     * The rung to test first: the LOWEST of what the Parent's answers suggest,
     * so a child is never opened with something the Parent says is too hard
     * (the check climbs on its own if it turns out to be easy).
     *
     * Two things the Parent gave at sign up are used: how they describe the
     * child's reading, and the three grade specific yes/no questions. A child
     * without either (added before those were stored) starts from the level
     * computed at the time.
     */
    public static function startingRung(Learner $learner): string
    {
        $hasLetters = self::hasLettersRung($learner);
        $signals = [];

        $stageMap = config('diagnostic.stage_start');
        if ($learner->reading_stage !== null && isset($stageMap[$learner->reading_stage])) {
            $signals[] = $stageMap[$learner->reading_stage];
        }

        $answers = is_array($learner->placement_answers) ? $learner->placement_answers : [];
        if (isset($answers['q1'], $answers['q2'], $answers['q3'])) {
            $yes = count(array_filter([$answers['q1'], $answers['q2'], $answers['q3']], fn ($a) => $a === 'yes'));

            if ($hasLetters) {
                // Grade 1's questions: names letters / says their sounds / blends
                // them into simple words.
                $signals[] = match (true) {
                    $answers['q1'] === 'no' => 0,
                    $yes === 3 => 3,
                    $answers['q3'] === 'yes' => 2,
                    default => 1,
                };
            } else {
                $signals[] = match (true) {
                    $yes === 3 => 3,
                    $yes === 2 => 2,
                    default => 1,
                };
            }
        }

        $position = $signals === []
            ? (config('diagnostic.legacy_mastery_start')[$learner->mastery_level] ?? 2)
            : min($signals);

        if (! $hasLetters) {
            $position = max($position, 1);
        }

        return self::SCALE[$position];
    }

    public static function rungOf(Activity $activity): string
    {
        return $activity->isLetterCheck() ? self::LETTERS : strtolower((string) $activity->difficulty_tier);
    }

    /**
     * A 0 to 100 starting score in the adaptive recommender's own scale for
     * a child who landed on $rung with $accuracy percent right there.
     */
    public static function score(string $rung, float $accuracy, bool $hasLetters): float
    {
        $bands = config('diagnostic.placement_bands');

        [$low, $high] = match ($rung) {
            self::LETTERS => $bands['letters'],
            'easy' => $hasLetters ? $bands['easy_after_letters'] : $bands['easy'],
            'medium' => $bands['medium'],
            default => $bands['hard'],
        };

        $share = max(0.0, min(100.0, $accuracy)) / 100;

        return round($low + ($high - $low) * $share, 2);
    }
}

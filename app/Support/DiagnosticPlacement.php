<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Learner;

/**
 * Where the first-login reading check starts, which content it needs, and how
 * its result is turned into a level and a starting score. The rungs and the
 * numbers live in config/diagnostic.php; this class only applies them.
 *
 * Nothing here looks at which grade the child is enrolled in, except to know
 * which set of three yes/no questions the Parent answered (they are different
 * questions per grade). The check is about where the child is, so a Grade 3
 * child who cannot read starts on letters and a Grade 2 child who reads well
 * starts on a passage.
 */
class DiagnosticPlacement
{
    public const LETTERS = 'letters';

    /**
     * @return list<string> Every rung key, lowest first.
     */
    public static function ladder(): array
    {
        return array_keys(config('diagnostic.ladder'));
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
        $ladder = self::ladder();
        $signals = [];

        $stageMap = config('diagnostic.stage_start');
        if ($learner->reading_stage !== null && isset($stageMap[$learner->reading_stage])) {
            $signals[] = $stageMap[$learner->reading_stage];
        }

        $answers = is_array($learner->placement_answers) ? $learner->placement_answers : [];
        if (isset($answers['q1'], $answers['q2'], $answers['q3'])) {
            $yes = count(array_filter([$answers['q1'], $answers['q2'], $answers['q3']], fn ($a) => $a === 'yes'));
            $grade = (int) substr((string) $learner->grade_level, 6);

            $signals[] = match ($grade) {
                // Names letters / says their sounds / blends them into simple words.
                1 => match (true) {
                    $answers['q1'] === 'no' => 0,
                    $yes === 3 => 3,
                    $answers['q3'] === 'yes' => 2,
                    default => 1,
                },
                // Reads short sentences alone / knows sight words / answers a question
                // about what they read. A child who cannot read short sentences is still
                // an early phonics reader at best; each further yes is a rung higher.
                2 => $answers['q1'] === 'yes' ? 3 + ($yes - 1) : ($yes >= 2 ? 2 : 1),
                // Reads a paragraph smoothly / gives the main idea / works out a word
                // from context: a child who can do the first is already on passages.
                default => $answers['q1'] === 'yes' ? 4 + ($yes - 1) : ($yes >= 2 ? 2 : 1),
            };
        }

        $position = $signals === []
            ? (config('diagnostic.legacy_mastery_start')[$learner->mastery_level] ?? 2)
            : min($signals);

        return $ladder[max(0, min(count($ladder) - 1, $position))];
    }

    /**
     * The rungs a check starting at $start can reach: at most three items, one
     * step per item after the first.
     *
     * @return list<string>
     */
    public static function reachableRungs(string $start): array
    {
        $ladder = self::ladder();
        $index = array_search($start, $ladder, true);
        $index = $index === false ? 0 : $index;
        $reach = (int) config('diagnostic.reach', 2);
        $from = max(0, $index - $reach);
        $to = min(count($ladder) - 1, $index + $reach);

        return array_slice($ladder, $from, $to - $from + 1);
    }

    /**
     * What the generator has to be asked for to cover these rungs. One request
     * returns all three tiers of one (grade, competency, activity type), so
     * rungs that share those share a request. The letters rung needs none.
     *
     * @param  list<string>  $rungs
     * @return array<string, array{grade: int, competency: string, activity_type: string, tiers: array<string, string>}>
     */
    public static function generationGroups(array $rungs): array
    {
        $groups = [];

        foreach ($rungs as $key) {
            $rung = config("diagnostic.ladder.{$key}");
            if (($rung['kind'] ?? null) !== 'generated') {
                continue;
            }

            $groupKey = "{$rung['grade']}:{$rung['competency']}:{$rung['activity_type']}";
            $groups[$groupKey] ??= [
                'grade' => $rung['grade'],
                'competency' => $rung['competency'],
                'activity_type' => $rung['activity_type'],
                'tiers' => [],
            ];
            $groups[$groupKey]['tiers'][$rung['tier']] = $key;
        }

        return $groups;
    }

    /**
     * The rung an activity belongs to. Items made before the ladder existed
     * (a check that was already running) were plain easy/medium/hard tiers of
     * the child's own grade and keep that name.
     */
    public static function rungOf(Activity $activity): string
    {
        if ($activity->isLetterCheck()) {
            return self::LETTERS;
        }

        $grade = (int) substr((string) $activity->grade_level, 6);
        $tier = strtolower((string) $activity->difficulty_tier);

        foreach (config('diagnostic.ladder') as $key => $rung) {
            if (($rung['kind'] ?? null) === 'generated'
                && $rung['grade'] === $grade
                && $rung['competency'] === $activity->competency
                && $rung['activity_type'] === $activity->activity_type
                && $rung['tier'] === $tier) {
                return $key;
            }
        }

        return $tier;
    }

    /** The app's level (Beginning / Developing / Proficient) for a rung. */
    public static function masteryOf(string $rung): string
    {
        return config("diagnostic.ladder.{$rung}.mastery")
            ?? config("diagnostic.legacy_tiers.{$rung}.mastery")
            ?? 'Developing';
    }

    /**
     * A 0 to 100 starting score in the adaptive recommender's own scale for a
     * child who landed on $rung with $accuracy percent right there: inside the
     * rung's own slice of the recommender's bands, and higher the better they
     * did there.
     */
    public static function score(string $rung, float $accuracy): float
    {
        [$low, $high] = config("diagnostic.ladder.{$rung}.band")
            ?? config("diagnostic.legacy_tiers.{$rung}.band")
            ?? [60, 84];

        $share = max(0.0, min(100.0, $accuracy)) / 100;

        return round($low + ($high - $low) * $share, 2);
    }
}

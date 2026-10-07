<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Learner;
use Illuminate\Support\Collection;

/**
 * Can this child (or this group of children) read this activity? One answer for every place an activity is given or
 * suggested: the class window, the activity window, the one click suggestions and the alerts, on the server, so nothing
 * can be forced through by leaving the screen's note out.
 *
 * It looks at where the CHILD is (ReadingLevel::readiness: the first reading check, or what the Parent said when
 * there is no check yet) against how long the activity is (config/activity_fit.php). It never moves a child and never
 * changes an activity: it says ok, caution (a stretch, can still be given) or blocked (too long, cannot be given).
 */
class ActivityFit
{
    public const OK = 'ok';
    public const CAUTION = 'caution';
    public const BLOCKED = 'blocked';
    public const UNKNOWN = 'unknown';

    /** [comfortable, stretch] words for a rung. */
    public static function limits(int $rung): array
    {
        $limits = config('activity_fit.limits');

        return $limits[max(0, min(max(array_keys($limits)), $rung))];
    }

    /** The activity's length as it counts for a child at this rung (connected text counts more for the earliest rungs). */
    public static function effectiveWords(Activity $activity, int $rung): float
    {
        $words = (float) $activity->word_count;

        if (in_array($rung, config('activity_fit.connected_text_rungs'), true)
            && in_array($activity->activity_type, config('activity_fit.connected_text_types'), true)) {
            return $words * (float) config('activity_fit.connected_text_weight');
        }

        return $words;
    }

    /**
     * @return array{verdict:string, rung:?int, source:?string, words:int, comfortable:?int, stretch:?int, step:?string, note:?string}
     */
    public static function forLearner(Activity $activity, Learner $learner): array
    {
        $readiness = ReadingLevel::readiness($learner);
        $words = (int) $activity->word_count;

        if ($readiness === null || $words <= 0) {
            return ['verdict' => self::UNKNOWN, 'rung' => null, 'source' => null, 'words' => $words, 'comfortable' => null, 'stretch' => null, 'step' => null, 'note' => null];
        }

        [$comfortable, $stretch] = self::limits($readiness['rung']);
        $effective = self::effectiveWords($activity, $readiness['rung']);
        $step = ReadingLevel::stepNameForRung($readiness['rung']);

        $verdict = match (true) {
            $effective <= $comfortable => self::OK,
            $effective <= $stretch => self::CAUTION,
            default => self::BLOCKED,
        };

        $where = "{$learner->first_name} reads at the {$step} step ({$readiness['sourceText']})";
        $note = match ($verdict) {
            self::BLOCKED => "{$where}, and \"{$activity->title}\" has {$words} words. A child at that step reads about {$comfortable} words at a time, so this is too long for {$learner->first_name} and cannot be assigned yet.",
            self::CAUTION => "{$where}. \"{$activity->title}\" has {$words} words, a stretch for that step (about {$comfortable} words is comfortable). It can be assigned, with help from you.",
            default => null,
        };

        return ['verdict' => $verdict, 'rung' => $readiness['rung'], 'source' => $readiness['source'], 'words' => $words, 'comfortable' => $comfortable, 'stretch' => $stretch, 'step' => $step, 'note' => $note];
    }

    /**
     * The answer for a group of learners (a class, a reading group, a focus group).
     *
     * @param  iterable<Learner>  $learners
     * @param  string  $audience  how to name them in a sentence: "this class", "the Needs most support group"
     * @return array{verdict:string, note:?string, blocked:int, caution:int, known:int, total:int, blockedNames:list<string>}
     */
    public static function forAudience(Activity $activity, iterable $learners, string $audience): array
    {
        $blocked = $caution = $known = $total = 0;
        $blockedNames = [];
        $fitsAll = [];

        foreach ($learners as $learner) {
            $total++;
            $one = self::forLearner($activity, $learner);

            if ($one['verdict'] === self::UNKNOWN) {
                continue;
            }

            $known++;
            if ($one['verdict'] === self::BLOCKED) {
                $blocked++;
                $blockedNames[] = $learner->first_name;
            } elseif ($one['verdict'] === self::CAUTION) {
                $caution++;
            }
        }

        if ($known === 0) {
            return ['verdict' => self::UNKNOWN, 'note' => null, 'blocked' => 0, 'caution' => 0, 'known' => 0, 'total' => $total, 'blockedNames' => []];
        }

        $words = (int) $activity->word_count;
        $verdict = match (true) {
            $blocked > 0 && ($blocked / $known) >= (float) config('activity_fit.block_share') => self::BLOCKED,
            $blocked > 0 || $caution > 0 => self::CAUTION,
            default => self::OK,
        };

        $names = self::nameList($blockedNames);
        $note = match ($verdict) {
            self::BLOCKED => "\"{$activity->title}\" has {$words} words, which is too long for {$blocked} of the {$known} learners in {$audience} ({$names}). It cannot be assigned to {$audience}. Give it only to a reading group that can read it, choose a shorter activity, or generate Easy activities for the learners who need them.",
            self::CAUTION => $blocked > 0
                ? "\"{$activity->title}\" has {$words} words, which is too long for {$blocked} of {$known} learners in {$audience} ({$names}). It can still be assigned, but they will find it very hard; a shorter activity for them would help."
                : "\"{$activity->title}\" has {$words} words, a stretch for {$caution} of {$known} learners in {$audience}. It can be assigned, with help from you.",
            default => null,
        };

        return ['verdict' => $verdict, 'note' => $note, 'blocked' => $blocked, 'caution' => $caution, 'known' => $known, 'total' => $total, 'blockedNames' => $blockedNames];
    }

    /** "Ana", "Ana and Ben", "Ana, Ben and Cora", "Ana, Ben, Cora and 2 more". */
    private static function nameList(array $names): string
    {
        $names = array_values(array_unique($names));
        $count = count($names);

        return match (true) {
            $count === 0 => '',
            $count === 1 => $names[0],
            $count <= 3 => implode(', ', array_slice($names, 0, -1)).' and '.$names[$count - 1],
            default => implode(', ', array_slice($names, 0, 3)).' and '.($count - 3).' more',
        };
    }

    /**
     * Does it fit well enough to be offered as a suggestion? An activity that is a stretch or too long is never
     * suggested on its own; the Teacher can still choose it from the full list (a stretch only, never a blocked one).
     *
     * @param  iterable<Learner>  $learners
     */
    public static function suitsAll(Activity $activity, iterable $learners): bool
    {
        foreach ($learners as $learner) {
            if (self::forLearner($activity, $learner)['verdict'] === self::BLOCKED) {
                return false;
            }
        }

        return true;
    }

    /**
     * How well an activity's length matches the learners (1 is the best, 0 is far from it), for ranking suggestions: the
     * closest to the comfortable length without going over comes first. Unknown learners count as a fit.
     *
     * @param  Collection<int, Learner>  $learners
     */
    public static function closeness(Activity $activity, Collection $learners): float
    {
        $scores = [];

        foreach ($learners as $learner) {
            $readiness = ReadingLevel::readiness($learner);
            if ($readiness === null || (int) $activity->word_count <= 0) {
                continue;
            }

            [$comfortable] = self::limits($readiness['rung']);
            $effective = self::effectiveWords($activity, $readiness['rung']);
            // Over the comfortable length is worse than under it; a very short text for a strong reader is a little off too.
            $scores[] = $effective <= $comfortable
                ? 0.6 + 0.4 * ($effective / max(1, $comfortable))
                : max(0.0, 0.6 - 0.6 * (($effective - $comfortable) / max(1, $comfortable)));
        }

        return $scores === [] ? 0.5 : array_sum($scores) / count($scores);
    }
}

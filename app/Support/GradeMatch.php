<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\Teacher;

/**
 * A grade is only for its own grade, with no exception: a Grade 1 class is only for Grade 1 learners and only gets Grade 1
 * activities, a Grade 2 class is only for Grade 2, and so on, even when the teacher handles several grades (such a teacher opens one
 * class for each grade). The class side of this is SchoolClass::acceptsGrade; this is the ACTIVITY side, and the teacher's own side.
 *
 * One place for it, called from every Teacher screen and service that gives or suggests an activity, so the website, the phone app and
 * the one click suggestions cannot disagree. Every answer comes with a plain note that says what to do instead, and a verdict of
 * ActivityFit::BLOCKED (so the screens that already know how to say "this cannot be assigned here" and switch the button off, do).
 * `reason` is 'grade' so a screen can tag it "Other grade" instead of "Too long".
 *
 * A learner or an activity with no grade on record is never turned away for it: there is nothing to compare.
 *
 * The Learner side is deliberately NOT touched: what a child sees and how their reading counts never depends on this (see ActivityFit,
 * which only answers "can this child read it").
 */
class GradeMatch
{
    public const REASON = 'grade';

    public static function differs(?string $activityGrade, ?string $otherGrade): bool
    {
        return filled($activityGrade) && filled($otherGrade) && $activityGrade !== $otherGrade;
    }

    /** One learner: the activity's grade must be the learner's own, otherwise it is blocked; if it matches, whether they can read it (ActivityFit). */
    public static function forLearner(Activity $activity, Learner $learner): array
    {
        if (self::differs($activity->grade_level, $learner->grade_level)) {
            return [
                'verdict' => ActivityFit::BLOCKED, 'rung' => null, 'source' => null, 'words' => (int) $activity->word_count, 'comfortable' => null, 'stretch' => null, 'step' => null,
                'reason' => self::REASON,
                'note' => "{$learner->first_name} is in {$learner->grade_level}, and \"{$activity->title}\" is a {$activity->grade_level} activity. A learner only gets activities of their own grade. Give this one to a {$activity->grade_level} learner, or write a {$learner->grade_level} activity (Activities, Write new).",
            ];
        }

        return ActivityFit::forLearner($activity, $learner);
    }

    /**
     * A class, or a group of classes that share a tag. `$classes` are the classes the audience belongs to (one for a class, every class
     * carrying the tag for a group). If any of them is another grade it is blocked, naming them; a learner of another grade left in a
     * class (older data) blocks it too, until they are moved. Otherwise it is whether they can read it (ActivityFit).
     *
     * @param  iterable<Learner>  $learners
     * @param  iterable<SchoolClass>  $classes
     */
    public static function forAudience(Activity $activity, iterable $learners, string $audience, iterable $classes = []): array
    {
        $classes = collect($classes);
        $learners = collect($learners);
        $total = $learners->count();
        $blocked = fn (string $note) => ['verdict' => ActivityFit::BLOCKED, 'note' => $note, 'blocked' => 0, 'caution' => 0, 'known' => 0, 'total' => $total, 'blockedNames' => [], 'reason' => self::REASON];

        $wrong = $classes->filter(fn (SchoolClass $c) => self::differs($activity->grade_level, $c->grade_level))->values();

        if ($wrong->isNotEmpty()) {
            if ($classes->count() === 1) {
                $c = $wrong->first();

                return $blocked("\"{$activity->title}\" is a {$activity->grade_level} activity and {$c->name} is a {$c->grade_level} class. A {$c->grade_level} class only gets {$c->grade_level} activities, even if you handle more than one grade. Give it to one of your {$activity->grade_level} classes (Classes, New class if you do not have one).");
            }

            $names = self::list($wrong->map(fn (SchoolClass $c) => "{$c->name} ({$c->grade_level})")->all());

            return $blocked("{$audience} includes {$names}. \"{$activity->title}\" is a {$activity->grade_level} activity, and an activity only goes to {$activity->grade_level} classes. Give it to your {$activity->grade_level} classes one at a time, or keep each group tag to one grade.");
        }

        $odd = $learners->filter(fn (Learner $l) => self::differs($activity->grade_level, $l->grade_level));

        if ($odd->isNotEmpty()) {
            $names = self::list($odd->map(fn (Learner $l) => "{$l->first_name} ({$l->grade_level})")->values()->all());

            return $blocked("{$names} ".($odd->count() === 1 ? 'is' : 'are')." in {$audience} but ".($odd->count() === 1 ? 'is' : 'are')." not in {$activity->grade_level}, and \"{$activity->title}\" is a {$activity->grade_level} activity. Move ".($odd->count() === 1 ? 'them' : 'them')." to a class of their own grade first, then assign it.");
        }

        return ActivityFit::forAudience($activity, $learners, $audience);
    }

    /**
     * The grades a Teacher may use for something new (writing an activity, opening a class): the ones they handle. A forged request for
     * another grade is refused on the server with this sentence.
     */
    public static function teacherRefusal(Teacher $teacher, string $grade, string $what): ?string
    {
        $handled = $teacher->gradesAllowed();

        if (in_array($grade, $handled, true)) {
            return null;
        }

        return 'You handle '.self::list($handled)." only, so you cannot {$what} for {$grade}. A {$grade} activity or class is for {$grade} learners. Change the grades you handle in Profile if that is wrong.";
    }

    /** "Ana", "Ana and Ben", "Ana, Ben and Cai", "Ana, Ben, Cai and 2 more". */
    private static function list(array $items): string
    {
        $items = array_values($items);
        $count = count($items);

        return match (true) {
            $count === 0 => '',
            $count === 1 => $items[0],
            $count <= 3 => implode(', ', array_slice($items, 0, -1)).' and '.$items[$count - 1],
            default => implode(', ', array_slice($items, 0, 3)).' and '.($count - 3).' more',
        };
    }
}

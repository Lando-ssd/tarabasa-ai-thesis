<?php

namespace App\Support;

use App\Models\Learner;
use App\Models\SchoolClass;

/**
 * The general reading level of a class, and whether a learner may be added to it.
 *
 * A teacher's own instruction: a class is taught at one general level, and a learner who already reads at a high level
 * cannot be added to it, even when they are in the same grade, because the class is not pitched for them. Such a child
 * belongs in a class whose general level is high (a higher class), where their reading will be taught properly.
 *
 * "High" is the Independent level (Phil-IRI: reads on their own). The class's general level is the middle level of the
 * learners already in it, once at least three have a level; before that it is the usual level for the class's grade
 * (a Grade 1 class is taught at the Instructional level; Grade 2 and Grade 3 classes may hold Independent readers).
 * Nothing here moves a child or changes a class: it only says yes, or says why not.
 */
class ClassLevel
{
    /** Grade of a class => the band it is taught at when it has too few learners with a level to say. */
    public const GRADE_DEFAULT = ['Grade 1' => 'instructional', 'Grade 2' => 'independent', 'Grade 3' => 'independent'];

    /** A class has a general level of its own once this many of its learners have a level. */
    public const MIN_LEARNERS = 3;

    /** Where the child reads, as a Phil-IRI band, or null when nothing is known about them. */
    public static function bandOf(Learner $learner): ?string
    {
        $readiness = ReadingLevel::readiness($learner);

        // What a parent said at sign up is a starting point, not a measured level, so it is never used to turn a child away.
        if ($readiness === null || $readiness['source'] === 'parent') {
            return null;
        }

        return match (true) {
            $readiness['rung'] <= 0 => 'non',
            $readiness['rung'] <= 2 => 'frustration',
            $readiness['rung'] <= 4 => 'instructional',
            default => 'independent',
        };
    }

    /**
     * @return array{band:string, label:string, source:'learners'|'grade'}
     */
    public static function general(SchoolClass $class): array
    {
        $orders = $class->learners->map(fn (Learner $l) => ReadingLevel::BAND_ORDER[self::bandOf($l) ?? ''] ?? null)->filter(fn ($o) => $o !== null)->sort()->values();

        if ($orders->count() >= self::MIN_LEARNERS) {
            $band = array_search($orders[intdiv($orders->count(), 2)], ReadingLevel::BAND_ORDER, true);

            return ['band' => $band, 'label' => ReadingLevel::bandShort($band), 'source' => 'learners'];
        }

        $band = self::GRADE_DEFAULT[$class->grade_level] ?? 'instructional';

        return ['band' => $band, 'label' => ReadingLevel::bandShort($band), 'source' => 'grade'];
    }

    /**
     * Why this learner cannot be added to this class, or null when they can.
     */
    public static function refusal(SchoolClass $class, Learner $learner): ?string
    {
        if (self::bandOf($learner) !== 'independent') {
            return null;
        }

        $general = self::general($class);

        if ($general['band'] === 'independent') {
            return null;
        }

        $where = $general['source'] === 'learners'
            ? "the general level of the learners already in {$class->name} ({$general['label']})"
            : "the general level of a {$class->grade_level} class ({$general['label']})";

        return "{$learner->first_name} reads at the Independent level, above {$where}. A learner who already reads this well cannot be added here, even in the same grade. They belong in a class taught at a higher level. Ask the school to place them in one.";
    }
}

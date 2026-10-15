<?php

namespace App\Support;

use App\Models\Learner;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Services\TeacherAlerts;
use Illuminate\Support\Collection;

/**
 * How a Teacher's classes and learners are told to the phone app: plain arrays, one place.
 *
 * Every word and number comes from the rules the website already uses (ReadingLevel for the step and the Phil-IRI level,
 * ClassLevel for the class's general level, TeacherAlerts for who needs support or is ready to move up), so the phone
 * cannot say something the website would not.
 */
class TeacherRoster
{
    /**
     * One short tag for each learner who has an open alert, in the same priority as the Alerts screen (needs support, just
     * starting, moved up or ready to, quiet). A learner with nothing open has no tag.
     *
     * @return array<int, array{key: string, label: string, tone: string}> keyed by learner id
     */
    public static function tags(Teacher $teacher): array
    {
        $tags = [];

        foreach (app(TeacherAlerts::class)->forTeacher($teacher, withSuggestions: false) as $alert) {
            $id = $alert['learner']->id;
            if (isset($tags[$id])) {
                continue;
            }

            $tags[$id] = match (true) {
                $alert['kind'] === 'support' => ['key' => 'support', 'label' => 'Needs support', 'tone' => 'red'],
                $alert['kind'] === 'start' => ['key' => 'start', 'label' => 'Starting to read', 'tone' => 'blue'],
                $alert['kind'] === 'up' && ($alert['variant'] ?? null) === 'moved' => ['key' => 'moved', 'label' => 'Moved up', 'tone' => 'green'],
                $alert['kind'] === 'up' => ['key' => 'up', 'label' => 'Ready to move up', 'tone' => 'green'],
                default => ['key' => 'quiet', 'label' => 'Quiet '.(preg_match('/^(\d+)/', (string) ($alert['evidence'] ?? ''), $m) ? $m[1].' days' : 'for a while'), 'tone' => 'amber'],
            };
        }

        return $tags;
    }

    /**
     * @param  array<int, array{key: string, label: string, tone: string}>  $tags
     * @param  array<int, array{direction: string, text: string}>  $flags  ReadingLevel::levelChecks for the class
     */
    public static function learner(Learner $learner, array $tags = [], array $flags = []): array
    {
        $readiness = ReadingLevel::readiness($learner);
        $band = ReadingLevel::band($learner);

        return [
            'id' => $learner->id,
            'firstName' => $learner->first_name,
            'lastName' => $learner->last_name,
            'name' => trim($learner->first_name.' '.$learner->last_name),
            'code' => $learner->learner_code,
            'grade' => $learner->grade_level,
            // Phil-IRI level and the step on the ladder, in a teacher's words. "Not checked yet" until the first reading check is done.
            'level' => $band === 'unchecked' ? 'Not checked yet' : ReadingLevel::bandShort($band),
            'step' => $readiness && $learner->reading_rung !== null ? (ReadingLevel::RUNG_LABELS[$readiness['rung']] ?? null) : null,
            'tag' => $tags[$learner->id] ?? null,
            'levelCheck' => $flags[$learner->id] ?? null,
        ];
    }

    /**
     * The card of one class: what it is, how many learners, how many activities it has been given and its reading groups.
     *
     * @param  array<int, Collection>  $activitiesByClass  TeacherClassService::activitiesByClass()
     */
    public static function classCard(SchoolClass $class, array $activitiesByClass): array
    {
        $groups = ReadingLevel::groups($class->learners);

        return [
            'id' => $class->id,
            'name' => $class->name,
            'grade' => $class->grade_level,
            'section' => $class->section,
            'groupTag' => $class->group_tag,
            'schoolYear' => $class->school_year,
            'readOnly' => SchoolClass::isYearPast($class->school_year),
            'learnerCount' => $class->learners->count(),
            'activityCount' => isset($activitiesByClass[$class->id]) ? $activitiesByClass[$class->id]->count() : 0,
            'groups' => collect(ReadingLevel::GROUPS)->map(fn ($g, $key) => ['key' => $key, 'title' => $g['title'], 'sub' => $g['sub'], 'count' => $groups[$key]->count()])->values()->all(),
        ];
    }
}

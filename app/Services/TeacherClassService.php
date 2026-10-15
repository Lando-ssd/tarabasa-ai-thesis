<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Support\ClassLevel;
use App\Support\LearnerCode;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Making a class, changing it, and putting a learner in it: the rules a Teacher's classes follow, in one place.
 *
 * The website's ClassController and the phone app's Api\TeacherApiController both call this, so the two cannot disagree
 * about who may be added to a class (one grade per class, the reading level guard, the limit on wrong codes, telling the
 * child's guardians). Nothing here knows about views, redirects or JSON: it returns data or throws a ValidationException.
 */
class TeacherClassService
{
    /**
     * What a new class needs. The grade must be one the Teacher says they handle (Profile), so a forged request cannot open a
     * class for another grade. A class is never edited into "becoming" next year's class (SchoolYear_Addition.txt): every
     * school year is a brand new row, so the school year is only given here, at creation.
     */
    public function createRules(Teacher $teacher): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'grade_level' => ['required', Rule::in($teacher->gradesAllowed())],
            'section' => ['required', 'string', 'max:255'],
            'group_tag' => ['nullable', 'string', 'max:255'],
            'school_year' => ['required', 'string', 'max:20'],
        ];
    }

    public function createMessages(Teacher $teacher): array
    {
        return [
            'grade_level.in' => 'You handle '.implode(' and ', $teacher->gradesAllowed()).'. Change the grades you handle in Profile to open a class for another grade.',
        ];
    }

    /** What changing a class needs. The class's own grade stays allowed even if the Teacher later narrowed the grades they handle, so an old class can still be renamed. */
    public function updateRules(Teacher $teacher, SchoolClass $class): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'grade_level' => ['required', Rule::in(array_unique([...$teacher->gradesAllowed(), $class->grade_level]))],
            'section' => ['required', 'string', 'max:255'],
            'group_tag' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function updateMessages(Teacher $teacher): array
    {
        return [
            'grade_level.in' => 'You handle '.implode(' and ', $teacher->gradesAllowed()).'. Change the grades you handle in Profile to use another grade.',
        ];
    }

    /** @param  array<string, mixed>  $validated  already checked with createRules() */
    public function create(Teacher $teacher, array $validated): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $teacher->id, ...$validated]);
    }

    /**
     * Saves a class's new details. A class holds ONE grade, so its grade cannot be changed under learners who would no
     * longer fit it (that would quietly merge grades). Nobody is moved or removed: the Teacher is told who is in the way.
     *
     * @param  array<string, mixed>  $validated  already checked with updateRules()
     */
    public function update(SchoolClass $class, array $validated): SchoolClass
    {
        $outOfPlace = $class->learners()->get()->reject(fn (Learner $l) => (new SchoolClass(['grade_level' => $validated['grade_level']]))->acceptsGrade($l->grade_level));

        if ($outOfPlace->isNotEmpty()) {
            $names = $outOfPlace->take(3)->map(fn (Learner $l) => "{$l->first_name} ({$l->grade_level})")->implode(', ');
            $more = $outOfPlace->count() > 3 ? ' and '.($outOfPlace->count() - 3).' more' : '';

            throw ValidationException::withMessages([
                'grade_level' => "{$names}{$more} would no longer fit: a {$validated['grade_level']} class is only for {$validated['grade_level']} learners. Move them to a class of their own grade first, then change this.",
            ]);
        }

        $class->update($validated);

        return $class;
    }

    /**
     * Put a learner in a class from a Learner Code, typed or scanned (the only way a Teacher connects to a learner).
     *
     * Every code starts with TB, so a Teacher only types the last 5 characters (the 4 digits and check digit of TB26-48293,
     * or the 5 digits of an older TB-12345); a whole code is accepted too. A wrong code is limited per teacher: the last 5
     * characters are only about ten thousand possibilities, and a teacher who tried them all would see every unenrolled
     * child's name. A learner already in a class cannot be joined elsewhere, and the class's grade and general reading level
     * must fit (ClassLevel::joinRefusal). The child's guardians are told, so a child is never added without them knowing.
     *
     * @return array{ok: true, learner: Learner}|array{ok: false, message: string}
     */
    public function join(User $user, Teacher $teacher, SchoolClass $class, string $typed): array
    {
        $joinKey = 'join-learner|teacher:'.$teacher->id;

        if (RateLimiter::tooManyAttempts($joinKey, 10)) {
            $minutes = (int) ceil(RateLimiter::availableIn($joinKey) / 60);

            return ['ok' => false, 'message' => "Too many codes that did not match. Please wait {$minutes} ".($minutes === 1 ? 'minute' : 'minutes').' and try again.'];
        }

        $typed = trim($typed);
        $tail = strtoupper(preg_replace('/^TB[0-9]{0,2}-?/i', '', preg_replace('/\s+/', '', $typed)));

        if (! LearnerCode::looksLikeCode($typed) && ! preg_match('/^[A-Z0-9]{5}$/', $tail)) {
            return ['ok' => false, 'message' => 'Type the last 5 characters of the Learner Code, like 48293.'];
        }

        $matches = LearnerCode::matching(LearnerCode::looksLikeCode($typed) ? $typed : $tail);

        if ($matches->count() > 1) {
            return ['ok' => false, 'message' => 'More than one learner ends in those characters. Type the whole code, like TB26-48293.'];
        }

        $learner = $matches->first();

        if (! $learner) {
            RateLimiter::hit($joinKey, 900);

            return ['ok' => false, 'message' => 'No learner found with that code. Check it and try again.'];
        }

        if ($learner->class_id !== null) {
            RateLimiter::hit($joinKey, 900);

            return ['ok' => false, 'message' => 'This learner is already enrolled in a class.'];
        }

        // A class holds one grade, and is taught at one general level: a learner of another grade, or one who already
        // reads well above the class, cannot be added (typed or scanned, the same answer).
        if ($refusal = ClassLevel::joinRefusal($teacher, $class->loadMissing('learners'), $learner)) {
            return ['ok' => false, 'message' => $refusal];
        }

        $learner->update(['class_id' => $class->id]);

        Notification::notifyForLearner(
            $learner,
            Notification::TYPE_CLASS_JOINED,
            "{$learner->first_name} was added to the class \"{$class->name}\" by {$user->first_name} {$user->last_name}".($teacher->school_name ? " ({$teacher->school_name})" : '').'.',
            includeTeacher: false
        );

        return ['ok' => true, 'learner' => $learner];
    }

    /**
     * Where a learner can be moved: this Teacher's OTHER classes of the same school year that take the learner's grade. A class holds
     * one grade, so a child is only ever offered classes of their own grade.
     *
     * @return Collection<int, SchoolClass>
     */
    public function moveTargets(Teacher $teacher, SchoolClass $class, Learner $learner): Collection
    {
        return SchoolClass::where('teacher_id', $teacher->id)
            ->where('school_year', $class->school_year)
            ->where('id', '!=', $class->id)
            ->orderBy('name')
            ->get()
            ->filter(fn (SchoolClass $other) => $other->acceptsGrade($learner->grade_level))
            ->values();
    }

    /**
     * Move a learner to another of this Teacher's classes in the same school year. The system only suggests this (the level check on
     * the roster); the Teacher decides. The learner's own grade is not touched: moving a strong reader to a higher class is a teaching
     * decision, not a promotion. The same grade and reading level rules as joining apply (ClassLevel::joinRefusal).
     *
     * @throws ValidationException with the message under `to_class_id`
     */
    public function move(Teacher $teacher, SchoolClass $class, Learner $learner, int $toClassId): SchoolClass
    {
        abort_if($class->teacher_id !== $teacher->id, 403);
        abort_if(SchoolClass::isYearPast($class->school_year), 403, 'Past school year classes are read-only.');
        abort_if($learner->class_id !== $class->id, 404);

        $target = SchoolClass::where('teacher_id', $teacher->id)
            ->where('school_year', $class->school_year)
            ->where('id', '!=', $class->id)
            ->find($toClassId);

        if (! $target) {
            throw ValidationException::withMessages(['to_class_id' => 'Choose one of your other classes.']);
        }

        if ($refusal = ClassLevel::joinRefusal($teacher, $target->loadMissing('learners'), $learner)) {
            throw ValidationException::withMessages(['to_class_id' => $refusal]);
        }

        $learner->update(['class_id' => $target->id]);

        return $target;
    }

    /**
     * What each class has been given: activities assigned to the class itself, and ones assigned
     * to a group tag the class carries. Only Approved activities count. A class assignment wins
     * over a group one for the same activity.
     *
     * @return array<int, Collection<int, array{activity: Activity, via: string, tag: ?string, on: mixed}>>
     */
    public function activitiesByClass(int $teacherId, Collection $classes): array
    {
        $assignments = ActivityAssignment::where('assigned_by_teacher_id', $teacherId)
            ->where(fn ($q) => $q->whereNotNull('class_id')->orWhereNotNull('group_tag'))
            ->with('activity')
            ->orderBy('assigned_at')
            ->get()
            ->filter(fn (ActivityAssignment $a) => $a->activity && $a->activity->status === 'Approved');

        $byClass = [];

        foreach ($classes as $class) {
            $rows = collect();

            foreach ($assignments as $a) {
                if ($a->class_id === $class->id && $a->reading_band === null) {
                    $rows->put($a->activity_id.'|', ['activity' => $a->activity, 'via' => 'class', 'tag' => null, 'band' => null, 'on' => $a->assigned_at]);
                } elseif ($a->class_id === $class->id) {
                    $rows->put($a->activity_id.'|'.$a->reading_band, ['activity' => $a->activity, 'via' => 'band', 'tag' => null, 'band' => $a->reading_band, 'on' => $a->assigned_at]);
                } elseif ($class->group_tag && $a->group_tag === $class->group_tag && ! $rows->has($a->activity_id.'|')) {
                    $rows->put($a->activity_id.'|', ['activity' => $a->activity, 'via' => 'group', 'tag' => $a->group_tag, 'band' => null, 'on' => $a->assigned_at]);
                }
            }

            // Given to the whole class (or through a focus group) already covers a reading group.
            $rows = $rows->reject(fn ($row, $key) => $row['via'] === 'band' && $rows->has($row['activity']->id.'|'));

            $byClass[$class->id] = $rows->values();
        }

        return $byClass;
    }
}

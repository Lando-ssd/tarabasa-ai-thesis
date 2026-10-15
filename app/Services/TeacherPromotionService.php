<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\PromotionRecord;
use App\Models\SchoolClass;
use App\Models\Teacher;
use Illuminate\Support\Facades\DB;

/**
 * Grade Promotions (Teacher Actor Prompt Step 9): a teacher releases a learner for the next grade, and any teacher with a matching class
 * claims them. The website's PromotionController and the phone app's Api\TeacherMoreApiController both call this.
 */
class TeacherPromotionService
{
    /**
     * Release: clears the learner's class and creates a Pending record. The learner stays fully Active, just unassigned until claimed by
     * any teacher (possibly a different one, possibly the same one next year) with a matching class. Grade 3 has no next grade.
     */
    public function release(Teacher $teacher, Learner $learner): PromotionRecord
    {
        $class = $learner->schoolClass;

        abort_unless($class && $class->teacher_id === $teacher->id, 403, 'That learner is not in one of your classes.');
        abort_if(SchoolClass::isYearPast($class->school_year), 403, 'Past school year classes are read-only.');

        $gradeNumber = (int) substr($learner->grade_level, 6);
        abort_if($gradeNumber >= 3, 403, 'Grade 3 has no next grade — this Learner cannot be released for promotion.');

        $record = PromotionRecord::create([
            'learner_id' => $learner->id,
            'released_by_teacher_id' => $teacher->id,
            'next_grade' => 'Grade '.($gradeNumber + 1),
            'status' => 'Pending',
            'released_from_class_id' => $class->id,
        ]);

        $learner->update(['class_id' => null]);

        return $record;
    }

    /**
     * Claim: the class AND the learner's grade are both set from the claimed class (the grade is never typed). The class is re-verified on
     * the server (ownership, grade match, school year in progress). Two teachers pressing Claim at the same moment: only one can win; the
     * record is locked and read again inside the lock.
     *
     * @return array{record: PromotionRecord, class: SchoolClass}
     */
    public function claim(Teacher $teacher, PromotionRecord $record, int $classId): array
    {
        abort_if($record->status !== 'Pending', 403, 'This promotion has already been claimed.');

        $class = SchoolClass::where('teacher_id', $teacher->id)
            ->where('id', $classId)
            ->where('school_year', SchoolClass::currentSchoolYear())
            ->first();

        abort_unless($class && $class->grade_level === $record->next_grade, 403, 'That class is not a valid claim target.');

        $claimed = DB::transaction(function () use ($record, $teacher, $class) {
            $locked = PromotionRecord::whereKey($record->id)->lockForUpdate()->first();
            if ($locked === null || $locked->status !== 'Pending') {
                return false;
            }

            $locked->update([
                'claimed_by_teacher_id' => $teacher->id,
                'status' => 'Claimed',
                'claimed_at' => now(),
                'claimed_into_class_id' => $class->id,
            ]);

            $locked->learner->update([
                'class_id' => $class->id,
                'grade_level' => $class->grade_level,
            ]);

            return true;
        });

        abort_unless($claimed, 403, 'This promotion has already been claimed.');

        return ['record' => $record->refresh(), 'class' => $class];
    }
}

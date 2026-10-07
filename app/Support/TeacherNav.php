<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\PromotionRecord;
use App\Models\SchoolClass;
use App\Models\User;

/**
 * The two counts the Teacher menu bar shows on every Teacher screen: open alerts, and released
 * learners this Teacher could claim right now. Worked out once, by the layout's view composer.
 */
class TeacherNav
{
    /**
     * @return array{unread: int, claim: int}
     */
    public static function counts(User $user): array
    {
        $teacher = $user->teacher;

        // The grades this teacher could claim a released learner into: the grade of each class, and every grade the
        // teacher handles when one of their classes is multigrade.
        $classes = $teacher
            ? SchoolClass::where('teacher_id', $teacher->id)->where('school_year', SchoolClass::currentSchoolYear())->get(['grade_level', 'multigrade'])
            : collect();
        $grades = $classes->pluck('grade_level')
            ->merge($classes->contains('multigrade', true) ? $teacher->gradesAllowed() : [])
            ->unique()
            ->values();

        return [
            // The number on the Alerts item: alerts that are open (who needs support, who is ready to
            // move up, who has gone quiet), not the routine reading summaries.
            'unread' => $teacher ? app(\App\Services\TeacherAlerts::class)->openCount($teacher) : 0,
            // Same rule as the Promotions screen: a released learner is claimable only if this
            // Teacher has a current-year class for the grade they are moving up to.
            'claim' => $grades->isEmpty() ? 0 : PromotionRecord::where('status', 'Pending')->whereIn('next_grade', $grades)->count(),
        ];
    }
}

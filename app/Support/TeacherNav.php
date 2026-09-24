<?php

namespace App\Support;

use App\Models\Notification;
use App\Models\PromotionRecord;
use App\Models\SchoolClass;
use App\Models\User;

/**
 * The two counts the Teacher menu bar shows on every Teacher screen: unread alerts, and released
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

        $grades = $teacher
            ? SchoolClass::where('teacher_id', $teacher->id)
                ->where('school_year', SchoolClass::currentSchoolYear())
                ->pluck('grade_level')
                ->unique()
            : collect();

        return [
            'unread' => Notification::where('recipient_user_id', $user->id)->where('is_read', false)->count(),
            // Same rule as the Promotions screen: a released learner is claimable only if this
            // Teacher has a current-year class for the grade they are moving up to.
            'claim' => $grades->isEmpty() ? 0 : PromotionRecord::where('status', 'Pending')->whereIn('next_grade', $grades)->count(),
        ];
    }
}

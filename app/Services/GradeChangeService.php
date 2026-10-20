<?php

namespace App\Services;

use App\Models\AdminAction;
use App\Models\Notification;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The Admin's side of "I handle another grade": an approved teacher asked to add a grade (AccountProfileService::updateGrades keeps the
 * request open), and the Admin approves or declines it here. Only an approval changes the grades a teacher may open classes for; a
 * decline leaves everything as it was. Both are written to the Activity log with the Admin's name and told to the teacher.
 *
 * Why an Admin decides, not the teacher: in a DepEd school it is the school head who assigns who teaches which grade (the class program),
 * and each class here holds one grade only, so a teacher who could add Grade 2 by ticking a box could open Grade 2 classes and take in
 * learners the school never gave them. The Admin checks with the school, the same way they check the employee ID and school at sign up.
 *
 * The website's Approvals screen is the only caller.
 */
class GradeChangeService
{
    /** What the Admin picks when declining. The teacher is told the reason. */
    public const DECLINE_REASONS = [
        'The school head did not confirm it',
        'Could not reach the school to check',
        'It is not on the teacher\'s class program',
        'Other',
    ];

    /** Requests waiting for the Admin: approved teachers with an open request for more grades, oldest first. */
    public static function pending(): Collection
    {
        return Teacher::with('user')
            ->where('status', 'Active')
            ->whereNotNull('grades_requested_at')
            ->orderBy('grades_requested_at')
            ->limit(100)
            ->get()
            ->filter(fn (Teacher $t) => $t->gradesPending() !== [])
            ->values();
    }

    public static function pendingCount(): int
    {
        return Teacher::where('status', 'Active')->whereNotNull('grades_requested_at')->count();
    }

    /**
     * Confirm the grades the teacher asked for: they become the grades the teacher handles, so class creation offers them and a class of
     * that grade can take learners. Existing classes are not touched.
     *
     * @return string what to show the Admin
     *
     * @throws ValidationException when there is nothing to approve any more (already decided, or the teacher cancelled)
     */
    public function approve(User $admin, Teacher $teacher): string
    {
        return DB::transaction(function () use ($admin, $teacher) {
            $teacher = Teacher::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $user = $teacher->user;
            $name = trim($user->first_name.' '.$user->last_name);
            $this->mustBeOpen($teacher, $name);

            $grades = $teacher->gradesPending();
            $added = array_values(array_diff($grades, $teacher->gradesAllowed()));

            $teacher->update(['grades_handled' => $grades, 'grades_requested' => null, 'grades_requested_at' => null, 'grades_request_note' => null]);

            AdminAction::record($admin, 'approved_grades', $user, 'Now handles '.implode(', ', $grades));
            $this->tell($user, Notification::TYPE_GRADES_APPROVED, 'The Admin confirmed your grades: '.implode(', ', $grades).'. You can now open a class for '.($added ? implode(' and ', $added) : 'each of them').'. Each class still holds one grade only.');

            return "{$name} now handles ".implode(', ', $grades).'.';
        });
    }

    /**
     * Decline the request. The teacher keeps the grades they had, is told the reason, and can ask again once the school has confirmed it.
     *
     * @return string what to show the Admin
     *
     * @throws ValidationException when there is nothing to decline any more
     */
    public function decline(User $admin, Teacher $teacher, string $reason, ?string $note): string
    {
        return DB::transaction(function () use ($admin, $teacher, $reason, $note) {
            $teacher = Teacher::whereKey($teacher->id)->lockForUpdate()->firstOrFail();
            $user = $teacher->user;
            $name = trim($user->first_name.' '.$user->last_name);
            $this->mustBeOpen($teacher, $name);

            $asked = array_values(array_diff($teacher->gradesPending(), $teacher->gradesAllowed()));
            $note = trim((string) $note);
            $why = $reason === 'Other' ? $note : $reason.($note !== '' ? ': '.$note : '');

            $teacher->update(['grades_requested' => null, 'grades_requested_at' => null, 'grades_request_note' => null]);

            AdminAction::record($admin, 'declined_grades', $user, 'Asked for '.($asked ? implode(', ', $asked) : 'a change'), $why);
            $this->tell($user, Notification::TYPE_GRADES_DECLINED, 'The Admin did not confirm '.($asked ? implode(' and ', $asked) : 'the grade change').' yet. Reason: '.$why.'. Your grades stay '.implode(', ', $teacher->gradesAllowed()).'. When your school has confirmed it, ask again from Profile.');

            return "{$name}'s request was declined. They have been told why.";
        });
    }

    private function mustBeOpen(Teacher $teacher, string $name): void
    {
        if ($teacher->status !== 'Active' || $teacher->gradesPending() === []) {
            throw ValidationException::withMessages(['admin' => "{$name} has no grade request waiting (it may have been decided already, or cancelled by the teacher). Nothing was changed."]);
        }
    }

    private function tell(User $teacherUser, string $type, string $message): void
    {
        Notification::create(['recipient_user_id' => $teacherUser->id, 'learner_id' => null, 'type' => $type, 'message' => mb_substr($message, 0, 500)]);
    }
}

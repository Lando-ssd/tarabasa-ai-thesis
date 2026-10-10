<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\SchoolClass;
use App\Services\TeacherAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * Teacher Actor Prompt Step 11: newest first, "Needs Attention"
     * visually distinct from routine "Session Summary" ones. No
     * per-Learner grouping requirement for Teacher (unlike Parent) — one
     * flat list is what's specified.
     */
    public function teacherIndex(Request $request, TeacherAlerts $alerts): View
    {
        $user = $request->user();
        $teacher = $user->teacher;
        $all = $alerts->forTeacher($teacher);

        $counts = [
            'all' => $all->count(),
            'support' => $all->where('kind', 'support')->count(),
            'start' => $all->where('kind', 'start')->count(),
            'up' => $all->where('kind', 'up')->count(),
            'quiet' => $all->where('kind', 'quiet')->count(),
        ];

        $filter = in_array($request->query('filter'), ['support', 'start', 'up', 'quiet'], true) ? $request->query('filter') : 'all';

        // The routine messages (a summary of each reading, a confirmed level) stay, folded away.
        $routine = Notification::where('recipient_user_id', $user->id)
            ->with('learner')
            ->orderByDesc('timestamp')->orderByDesc('id')
            ->limit(60)
            ->get();

        return view('teacher.notifications', [
            'user' => $user,
            'teacher' => $teacher,
            'alerts' => $filter === 'all' ? $all : $all->where('kind', $filter)->values(),
            'counts' => $counts,
            'filter' => $filter,
            'routine' => $routine,
            'unreadCount' => Notification::where('recipient_user_id', $user->id)->where('is_read', false)->count(),
        ]);
    }

    /** "Mark handled": hides the alert until the learner's readings change. */
    public function handled(Request $request, Learner $learner, TeacherAlerts $alerts): RedirectResponse
    {
        $teacher = $request->user()->teacher;
        $kind = $request->validate(['kind' => ['required', 'in:support,start,up,quiet']])['kind'];

        $this->authorizeLearner($teacher, $learner);
        $alerts->markHandled($teacher, $learner, $kind);

        return back()->with('status', "Marked handled for {$learner->first_name}.");
    }

    /** "Assign easier activity" / "Assign next level": gives the suggested activity to this one learner. */
    public function assignSuggested(Request $request, Learner $learner, TeacherAlerts $alerts): RedirectResponse
    {
        $teacher = $request->user()->teacher;
        $data = $request->validate([
            'kind' => ['required', 'in:support,start,up'],
            'activity_id' => ['required', 'integer'],
        ]);

        $this->authorizeLearner($teacher, $learner);

        // Only the Teacher's own approved activities can ever be given from here.
        $activity = Activity::where('created_by_teacher_id', $teacher->id)->where('status', 'Approved')->find($data['activity_id']);
        abort_if($activity === null, 403, 'That activity is not one of your approved activities.');

        $alerts->assign($teacher, $learner, $activity, $data['kind']);

        return back()->with('status', "\"{$activity->title}\" assigned to {$learner->first_name}.");
    }

    /** The learner must be in one of this Teacher's classes of the school year in progress. */
    private function authorizeLearner(\App\Models\Teacher $teacher, Learner $learner): void
    {
        $ok = SchoolClass::where('teacher_id', $teacher->id)
            ->where('school_year', SchoolClass::currentSchoolYear())
            ->where('id', $learner->class_id)
            ->exists();

        abort_unless($ok, 403, 'That learner is not in one of your classes.');
    }

    /**
     * Parent Actor Prompt Step 8: grouped into a clearly separate section
     * per Learner if more than one child is linked — never one blended
     * feed. Newest first within each group.
     */
    public function parentIndex(Request $request): View
    {
        $userId = $request->user()->id;
        $filter = $this->resolveFilter($request);

        $notifications = Notification::where('recipient_user_id', $userId)
            ->when($filter === 'attention', fn ($q) => $q->where('type', Notification::TYPE_NEEDS_ATTENTION))
            ->when($filter === 'routine', fn ($q) => $q->whereIn('type', [Notification::TYPE_SESSION_SUMMARY, Notification::TYPE_LEVEL_CONFIRMED, Notification::TYPE_GUARDIAN_LINKED, Notification::TYPE_CLASS_JOINED]))
            ->with('learner')
            ->orderByDesc('timestamp')
            ->get();

        $byLearner = $notifications->groupBy(fn ($n) => $n->learner->first_name ?? 'Unknown')
            ->map(fn ($group) => $group->groupBy(fn ($n) => $this->dayLabel($n->timestamp)));

        return view('parent.notifications', [
            'user' => $request->user(),
            'filter' => $filter,
            'byLearner' => $byLearner,
            'unreadCount' => Notification::where('recipient_user_id', $userId)->where('is_read', false)->count(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->recipient_user_id === $request->user()->id, 403);

        $notification->update(['is_read' => true]);

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        Notification::where('recipient_user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back();
    }

    private function resolveFilter(Request $request): string
    {
        return in_array($request->query('filter'), ['attention', 'routine'], true)
            ? $request->query('filter')
            : 'all';
    }

    private function dayLabel(\Illuminate\Support\Carbon $timestamp): string
    {
        if ($timestamp->isToday()) {
            return 'Today';
        }
        if ($timestamp->isYesterday()) {
            return 'Yesterday';
        }
        if ($timestamp->greaterThanOrEqualTo(now()->subDays(7))) {
            return 'This week';
        }

        return 'Earlier';
    }
}

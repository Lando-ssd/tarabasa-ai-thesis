<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Notification;
use App\Models\SchoolClass;
use App\Support\TeacherNav;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherDashboardController extends Controller
{
    /**
     * The Teacher Home. Viewable regardless of Active/Pending status (each linked screen handles
     * its own status-gating: approval gates "touching real students," not visibility).
     *
     * It answers "what needs me today": learners flagged for attention, drafts waiting to be
     * sorted into a level, released learners to claim, unread alerts, and a way into each class.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $teacher = $user->teacher;

        $needsAttention = Notification::where('recipient_user_id', $user->id)
            ->where('type', Notification::TYPE_NEEDS_ATTENTION)
            ->where('is_read', false)
            ->with('learner')
            ->orderByDesc('timestamp')
            ->get();

        $activities = Activity::where('created_by_teacher_id', $teacher->id)->get(['id', 'status']);

        $classes = SchoolClass::where('teacher_id', $teacher->id)
            ->where('school_year', SchoolClass::currentSchoolYear())
            ->withCount('learners')
            ->orderBy('name')
            ->get();

        $counts = TeacherNav::counts($user);

        return view('teacher.dashboard', [
            'user' => $user,
            'teacher' => $teacher,
            'classes' => $classes,
            'activitiesCount' => $activities->count(),
            'approvedCount' => $activities->where('status', 'Approved')->count(),
            'draftCount' => $activities->where('status', 'Draft')->count(),
            'claimCount' => $counts['claim'],
            'unreadNotifications' => $counts['unread'],
            'needsAttentionCount' => $needsAttention->count(),
            'latestNeedsAttention' => $needsAttention->first(),
        ]);
    }
}

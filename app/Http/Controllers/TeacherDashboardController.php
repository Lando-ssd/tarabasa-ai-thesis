<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Notification;
use App\Models\SchoolClass;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherDashboardController extends Controller
{
    /**
     * The real Teacher Dashboard — replaces the generic
     * dashboard-placeholder.blade.php for this role. Built from
     * docs/design-reference-html/tarabasa-teacher-dashboard.html as the
     * real foundation, with one real behavior correction: that prototype
     * locks Class Management/My Activities/Analytics entirely while
     * Pending, but those pages were already built (Sprint 3/5) to be
     * viewable while Pending — each handles its own locked/explained
     * state internally (Admin Actor Prompt: "approval gates touching
     * real students, not visibility"). Locking them again here would
     * contradict that already-shipped behavior, so only Generate Activity
     * (always unlocked) and the genuinely-unbuilt Promotions/Repository
     * (marked "Coming Soon", not status-gated) get special treatment.
     */
    public function index(Request $request): View
    {
        $teacher = $request->user()->teacher;
        $currentSchoolYear = SchoolClass::currentSchoolYear();

        // Design-audit addition: the dashboard's stat row was entirely
        // about the Teacher's own authoring activity (classes/activities/
        // credits), with nothing about how their actual students are
        // doing — unlike the Parent Dashboard's flagged-session alert.
        // Reuses the same real "Needs Attention" Notification rows
        // Notifications already computes correctly, rather than
        // re-deriving flagged status independently.
        $needsAttention = Notification::where('recipient_user_id', $request->user()->id)
            ->where('type', Notification::TYPE_NEEDS_ATTENTION)
            ->where('is_read', false)
            ->with('learner')
            ->orderByDesc('timestamp')
            ->get();

        return view('teacher.dashboard', [
            'user' => $request->user(),
            'teacher' => $teacher,
            'classesCount' => SchoolClass::where('teacher_id', $teacher->id)
                ->where('school_year', $currentSchoolYear)
                ->count(),
            'activitiesCount' => Activity::where('created_by_teacher_id', $teacher->id)->count(),
            'approvedCount' => Activity::where('created_by_teacher_id', $teacher->id)
                ->where('status', 'Approved')
                ->count(),
            'unreadNotifications' => Notification::where('recipient_user_id', $request->user()->id)
                ->where('is_read', false)
                ->count(),
            'needsAttentionCount' => $needsAttention->count(),
            'latestNeedsAttention' => $needsAttention->first(),
        ]);
    }
}

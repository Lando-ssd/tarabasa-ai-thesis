<?php

namespace App\Http\Controllers;

use App\Models\Notification;
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
    public function teacherIndex(Request $request): View
    {
        $userId = $request->user()->id;

        return view('teacher.notifications', [
            'user' => $request->user(),
            'filter' => $this->resolveFilter($request),
            'groups' => $this->groupedNotifications($userId, $this->resolveFilter($request)),
            'unreadCount' => Notification::where('recipient_user_id', $userId)->where('is_read', false)->count(),
        ]);
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
            ->when($filter === 'routine', fn ($q) => $q->whereIn('type', [Notification::TYPE_SESSION_SUMMARY, Notification::TYPE_LEVEL_CONFIRMED]))
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

    private function groupedNotifications(int $userId, string $filter): \Illuminate\Support\Collection
    {
        $notifications = Notification::where('recipient_user_id', $userId)
            ->when($filter === 'attention', fn ($q) => $q->where('type', Notification::TYPE_NEEDS_ATTENTION))
            ->when($filter === 'routine', fn ($q) => $q->whereIn('type', [Notification::TYPE_SESSION_SUMMARY, Notification::TYPE_LEVEL_CONFIRMED]))
            ->with('learner')
            ->orderByDesc('timestamp')
            ->get();

        return $notifications->groupBy(fn ($n) => $this->dayLabel($n->timestamp));
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

<?php

namespace App\Http\Controllers;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminController extends Controller
{
    /**
     * Admin Dashboard — Admin Actor Prompt, Section 3, Step 2.
     * Stat row is deliberately account-level only (Pending Teachers,
     * Active Teachers, Total Accounts) — no learner/content stats belong
     * here, per Section 1's "smaller, more auditable Admin surface".
     */
    public function dashboard(): View
    {
        $pendingTeachers = Teacher::with('user')
            ->where('status', 'Pending')
            ->get();

        // Admin never appears in its own "All Accounts" list (Rule 1) —
        // structurally excluded by this query, not just hidden in the view.
        $accounts = User::where('user_type', '!=', 'Admin')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.dashboard', [
            'pendingTeachers' => $pendingTeachers,
            'accounts' => $accounts,
            'pendingTeacherCount' => $pendingTeachers->count(),
            'activeTeacherCount' => Teacher::where('status', 'Active')->count(),
            'totalAccountCount' => $accounts->count(),
        ]);
    }

    /**
     * Sets a Teacher's verification status to Active. Takes effect
     * immediately on their next request if already logged in — nothing
     * is cached in the session, every gated action re-checks the DB.
     */
    public function activateTeacher(Teacher $teacher): RedirectResponse
    {
        $teacher->update(['status' => 'Active']);

        return back()->with('status', "{$teacher->user->first_name} {$teacher->user->last_name} activated.");
    }

    /**
     * Sets a Teacher's verification status to Rejected. Future login
     * attempts show a clear rejection message (enforced in AuthController).
     */
    public function rejectTeacher(Teacher $teacher): RedirectResponse
    {
        $teacher->update(['status' => 'Rejected']);

        return back()->with('status', "{$teacher->user->first_name} {$teacher->user->last_name} rejected.");
    }

    /**
     * Flips a User's account status Active <-> Inactive. Applies to
     * Teachers and Parents alike (Section 3's "All Accounts" section).
     * Structurally cannot target an Admin (Rule 1) — guarded here too,
     * not just by the query that built the list.
     */
    public function toggleUserStatus(User $user): RedirectResponse
    {
        abort_if($user->user_type === 'Admin', 403, 'Admin accounts cannot be toggled.');

        $user->update([
            'status' => $user->status === 'Active' ? 'Inactive' : 'Active',
        ]);

        return back()->with('status', "{$user->first_name} {$user->last_name} is now {$user->status}.");
    }
}

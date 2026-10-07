<?php

namespace App\Http\Controllers;

use App\Models\ServiceFailure;
use App\Models\Teacher;
use App\Models\User;
use App\Console\Commands\SyncAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminController extends Controller
{
    /**
     * Admin Dashboard — Admin Actor Prompt, Section 3, Step 2.
     * Stat row is deliberately account-level only (Pending Teachers,
     * Active Teachers, Total Accounts) — no learner/content stats belong
     * here, per Section 1's "smaller, more auditable Admin surface".
     */
    public function dashboard(Request $request): View
    {
        $pendingTeachers = Teacher::with('user')
            ->where('status', 'Pending')
            ->get();

        // Admin never appears in its own "All Accounts" list (Rule 1) —
        // structurally excluded by this query, not just hidden in the view.
        // Only the newest are listed (with a search for the rest): a school platform can have
        // thousands of accounts and a page that renders them all gets slower with every sign up.
        $q = is_string($request->query('q')) ? trim($request->query('q')) : '';
        $base = User::where('user_type', '!=', 'Admin');
        $total = (clone $base)->count();
        $accounts = $base
            ->when($q !== '', fn ($query) => $query->where(function ($w) use ($q) {
                $like = '%'.str_replace(['%', '_'], '', $q).'%'; // wildcards typed by the user mean nothing
                $w->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)->orWhere('email', 'like', $like);
            }))
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return view('admin.dashboard', [
            'pendingTeachers' => $pendingTeachers,
            'accounts' => $accounts,
            'pendingTeacherCount' => $pendingTeachers->count(),
            'activeTeacherCount' => Teacher::where('status', 'Active')->count(),
            'totalAccountCount' => $total,
            'accountQuery' => $q,
            // What the teammate services answered when a call failed (the newest 15, kept 14 days).
            'serviceFailures' => ServiceFailure::orderByDesc('id')->limit(15)->get(),
            // The Admin password this code used to be created with is public (it was in the
            // repository). While it is still in use the dashboard says so, loudly.
            'publishedPassword' => Hash::check(SyncAdminAccount::PUBLISHED_PASSWORD, (string) $request->user()->password),
        ]);
    }

    /**
     * "Check the services now": asks the activity generator, the reading checker and the adaptive recommender from
     * this very server whether they answer, and writes the result to the diary shown on this dashboard.
     */
    public function checkServices(\App\Services\ServiceCheck $check): RedirectResponse
    {
        // A sleeping free service can take a minute to answer its first request.
        set_time_limit(300);

        // One check at a time, and not again within a minute (it asks three outside services and writes a row).
        if (! \Illuminate\Support\Facades\Cache::add('service-check-running', true, 60)) {
            return back()->with('status', 'A check ran a moment ago. Please wait a minute, then look at "Recent service problems" below.');
        }

        $result = $check->runAndRecord();

        return back()->with('status', $result['summary'].' The details are in "Recent service problems" below.');
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

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeacherIsActive
{
    /**
     * The real security boundary the Admin Actor Prompt describes: Admin
     * approval gates "touching real students," not login itself. A Pending
     * Teacher can view Class Management (locked/explained), but every
     * mutating action — create a class, join a Learner, etc. — must be
     * rejected here on the backend, regardless of what the UI shows.
     *
     * Redirects back with a flash message rather than aborting, since a
     * legitimate session could hit this mid-use (e.g. an Admin flips them
     * back to Pending in another tab) — a clear explanation beats a crash
     * page even in that edge case.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $teacher = $request->user()?->teacher;

        if (! $teacher || $teacher->status !== 'Active') {
            return back()->with('classError', 'Your school verification is still pending. Once approved, you\'ll be able to create classes and add students.');
        }

        return $next($request);
    }
}

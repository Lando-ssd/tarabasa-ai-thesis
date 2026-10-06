<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsTeacher
{
    /**
     * Scopes every /teacher/* route to Teacher accounts only. A Parent or
     * Admin hitting these URLs directly gets a 403, not the page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->user_type !== 'Teacher') {
            abort(403, 'Teacher access required.');
        }

        // A teacher profile holds the school and employee number the Admin checks, so it cannot be
        // invented here. An account without one is refused clearly instead of crashing every screen.
        if ($request->user()->teacher === null) {
            abort(403, 'Your teacher profile is incomplete. Please contact the TaraBasa admin.');
        }

        return $next($request);
    }
}

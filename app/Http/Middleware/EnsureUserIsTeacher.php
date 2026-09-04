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

        return $next($request);
    }
}

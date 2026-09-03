<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * The real security boundary for every Admin route — checked on the
     * backend regardless of what links the frontend does or doesn't show.
     * A Teacher or Parent hitting an /admin/* URL directly gets a 403.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->user_type !== 'Admin') {
            abort(403, 'Admin access required.');
        }

        return $next($request);
    }
}

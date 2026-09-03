<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsParent
{
    /**
     * Scopes every /parent/* route to Parent accounts only. A Teacher or
     * Admin hitting these URLs directly gets a 403, not the page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || $request->user()->user_type !== 'Parent') {
            abort(403, 'Parent access required.');
        }

        return $next($request);
    }
}

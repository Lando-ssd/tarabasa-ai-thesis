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

        // A parent account always has its profile row (sign up makes both together). If one is ever
        // missing, make it here instead of letting every parent screen crash on it.
        if ($request->user()->parentProfile === null) {
            \App\Models\ParentAccount::firstOrCreate(['user_id' => $request->user()->id]);
            $request->user()->unsetRelation('parentProfile');
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * No screen in this app takes a LIST in its address (?period[]=x). Every filter, tab and id is a single
 * word or number. A request that sends one is not from the app, and several screens used to crash
 * with a 500 when they were handed one where they expected text (found by the system audit,
 * tests/Feature/AccessAuditTest). Refusing it once, here, covers every screen and any added later.
 */
class RejectNestedQuery
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach ($request->query() as $value) {
            if (is_array($value)) {
                abort(400, 'That address is not valid.');
            }
        }

        return $next($request);
    }
}

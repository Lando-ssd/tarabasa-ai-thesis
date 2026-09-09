<?php

namespace App\Http\Middleware;

use App\Services\LearnerDiagnosticService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The API counterpart of EnsureDiagnosticComplete — same rule (a Learner
 * cannot reach the dashboard/activities/games until their diagnostic is
 * done), but a mobile client has no concept of a Blade redirect, so this
 * responds with a real 403 + a machine-readable error code instead. Real
 * enforcement, not just a client-side route guard: a mobile app that
 * skipped straight to /api/learner/dashboard would still be rejected here.
 */
class EnsureDiagnosticCompleteApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $learner = $request->user();

        if (! app(LearnerDiagnosticService::class)->hasGenuinelyCompletedDiagnostic($learner)) {
            return response()->json([
                'error' => 'diagnostic_required',
                'message' => 'Complete the reading check before continuing.',
            ], 403);
        }

        return $next($request);
    }
}

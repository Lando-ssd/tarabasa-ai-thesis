<?php

namespace App\Http\Middleware;

use App\Models\ReadingSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDiagnosticComplete
{
    /**
     * PlacementDiagnostic_Addition.txt Part 2: "Run this check every time
     * a Learner successfully logs in... before showing the normal
     * Dashboard. If hasCompletedDiagnostic is false: route into the
     * Placement Test flow INSTEAD... The Learner cannot skip this or
     * reach 'Start Reading Activity' until it is completed." The
     * login-time redirect alone isn't enough — a bookmarked or
     * back-buttoned request could reach the dashboard directly, so this
     * is a standing guard on every route that check applies to, not just
     * a one-time redirect at login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $learner = $request->user('learner');

        $hasCompletedDiagnostic = ReadingSession::where('learner_id', $learner->id)
            ->where('session_type', 'Diagnostic')
            ->exists();

        if (! $hasCompletedDiagnostic) {
            return redirect()->route('learner.diagnostic.show');
        }

        return $next($request);
    }
}

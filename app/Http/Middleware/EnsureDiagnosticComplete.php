<?php

namespace App\Http\Middleware;

use App\Services\LearnerDiagnosticService;
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
     *
     * Delegates to LearnerDiagnosticService::hasGenuinelyCompletedDiagnostic()
     * — a real bug (found while building the mobile API, not caused by
     * it) meant this used to just check "does any Diagnostic session
     * exist," true after passage 1 of up to 3, letting a Learner who
     * bookmarked/back-buttoned to the dashboard mid-staircase through
     * before their real level was ever confirmed. See that method's own
     * doc comment.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $learner = $request->user('learner');

        if (! app(LearnerDiagnosticService::class)->hasGenuinelyCompletedDiagnostic($learner)) {
            return redirect()->route('learner.diagnostic.show');
        }

        return $next($request);
    }
}

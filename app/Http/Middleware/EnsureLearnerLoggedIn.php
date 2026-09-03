<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureLearnerLoggedIn
{
    /**
     * Checks the 'learner' guard specifically — completely independent of
     * whether a Parent/Teacher/Admin is logged in on the 'web' guard in the
     * same browser session (see config/auth.php).
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('learner')->check()) {
            return redirect()->route('learner.login');
        }

        return $next($request);
    }
}

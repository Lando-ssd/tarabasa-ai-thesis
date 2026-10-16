<?php

namespace App\Http\Middleware;

use App\Services\LearnerPinService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureLearnerLoggedIn
{
    /**
     * Checks the 'learner' guard specifically, completely independent of
     * whether a Parent/Teacher/Admin is logged in on the 'web' guard in the
     * same browser session (see config/auth.php).
     *
     * It also ends a website sign in whose PIN has since been changed by a
     * guardian (see LearnerPinService): the sign in remembers a fingerprint of the
     * PIN it was made with, and a different fingerprint means the PIN is new.
     * A sign in made before this existed has no fingerprint yet and is simply
     * given one, so nobody is signed out by the update itself.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('learner');

        if (! $guard->check()) {
            return redirect()->route('learner.login');
        }

        $learner = $guard->user();
        $stamp = LearnerPinService::stamp($learner);
        $known = $request->session()->get('learner_pin_stamp');

        // No fingerprint yet, or one that belongs to a different child (someone else signed in on this browser): take this child's as it is now.
        if ($known === null || ! str_starts_with((string) $known, $learner->id.':')) {
            $request->session()->put('learner_pin_stamp', $stamp);
        } elseif (! hash_equals((string) $known, $stamp)) {
            $guard->logout();
            $request->session()->forget('learner_pin_stamp');

            return redirect()->route('learner.login')->with('status', 'Your PIN was changed. Please sign in with the new PIN.');
        }

        return $next($request);
    }
}

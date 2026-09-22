<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Kept separate from AuthController — "reset a forgotten password" is a
 * distinct concern from "log in with a password you already know", same
 * reasoning already applied elsewhere in this app (e.g. LearnerReadingController
 * kept separate from LearnerAuthController).
 */
class ForgotPasswordController extends Controller
{
    public function showRequestForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Sends the real reset-link email via Laravel's built-in Password
     * broker. Never reveals whether the email actually exists — same
     * "don't leak which part was wrong" principle the login form already
     * follows — so the visible message is identical either way.
     */
    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $throttleKey = 'forgot-password|'.strtolower($request->email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Too many requests. Please try again in {$seconds} seconds.",
            ]);
        }
        RateLimiter::hit($throttleKey, 300);

        Password::sendResetLink($request->only('email'));

        // Deliberately not branching on the broker's real return status
        // (Password::INVALID_USER vs RESET_LINK_SENT) — surfacing that
        // difference would let someone probe which emails have accounts.
        return back()->with('status', 'If an account exists for that email, a reset link is on its way.');
    }

    public function showResetForm(Request $request, string $token)
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function reset(Request $request)
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function ($user, $password) {
                $user->forceFill(['password' => $password])->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __($status),
            ]);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. You can log in now.');
    }
}

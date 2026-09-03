<?php

namespace App\Http\Controllers;

use App\Models\ParentAccount;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showTeacherRegister()
    {
        return view('auth.register-teacher');
    }

    public function showParentRegister()
    {
        return view('auth.register-parent');
    }

    /**
     * Teacher registration — Module 1.1.1 + 1.1.5.
     * Creates the account, sends the real verification email, and logs
     * the Teacher in immediately with limited access (per the Admin
     * Actor Prompt: "a Pending Teacher can already log in to a
     * locked-down dashboard"). Admin approval is a separate gate that
     * happens later, not something this step waits on.
     */
    public function storeTeacher(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'school_name' => ['required', 'string', 'max:255'],
            'employee_id' => ['required', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:30'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'user_type' => 'Teacher',
                'contact_number' => $validated['contact_number'] ?? null,
            ]);

            Teacher::create([
                'user_id' => $user->id,
                'school_name' => $validated['school_name'],
                'employee_id' => $validated['employee_id'],
                'status' => 'Pending',
                'free_generation_credits_remaining' => 2,
            ]);

            return $user;
        });

        // Sends the real confirmation email (writes to storage/logs/laravel.log
        // right now, since MAIL_MAILER=log — switches to real Gmail delivery
        // the moment we update .env, no code change needed).
        $user->sendEmailVerificationNotification();

        // Log in immediately — verification does not block access, per the
        // actor prompt's explicit "logs in right away" rule.
        Auth::login($user);

        return view('auth.registration-submitted', [
            'firstName' => $user->first_name,
            'email' => $user->email,
            'role' => 'teacher',
        ]);
    }

    /**
     * Parent registration.
     *
     * NOTE: the manuscript's Parent screen (Figure 11) specifies a single
     * "full name" field, and this endpoint used to split it into
     * first/last name server-side to match. Per explicit instruction this
     * now uses separate First/Middle/Last inputs instead, matching the
     * Teacher registration pattern — a deliberate deviation from Figure 11,
     * not an oversight.
     * Same non-blocking verification approach as Teacher, for consistency.
     */
    public function storeParent(Request $request)
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'user_type' => 'Parent',
            ]);

            ParentAccount::create([
                'user_id' => $user->id,
            ]);

            return $user;
        });

        $user->sendEmailVerificationNotification();

        Auth::login($user);

        return view('auth.registration-submitted', [
            'firstName' => $user->first_name,
            'email' => $user->email,
            'role' => 'parent',
        ]);
    }

    /**
     * Handles the actual click on the verification link in the email.
     * Laravel's EmailVerificationRequest automatically checks the
     * signature is valid and hasn't expired before this code even runs.
     */
    public function verifyEmail(EmailVerificationRequest $request)
    {
        if (! $request->user()->hasVerifiedEmail()) {
            $request->fulfill();
        }

        return redirect()->route('login')->with('status', 'Email verified! You can now log in normally.');
    }

    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Shared login for Teacher/Parent/Admin — they all authenticate the
     * same mechanical way (email + password), then get routed differently
     * based on user_type. Per the Admin Actor Prompt: the error message
     * is always the same generic text whether the email doesn't exist or
     * the password is wrong — never reveal which one was incorrect.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'role' => ['nullable', 'string', Rule::in(['teacher', 'parent', 'admin'])],
        ]);

        // Rate limiting: 5 attempts per email+IP, then a 60-second cooldown.
        // Plain Laravel (RateLimiter facade) — unrelated to the old
        // Firebase-specific draft, which is superseded.
        $throttleKey = strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Invalid email or password.',
            ]);
        }

        // The role picked on the login form (Teacher/Parent/Admin login
        // links, or the general "Log in" link with no role at all) must
        // match the account's real user_type. Correct credentials on the
        // wrong role's form are rejected with a specific message rather
        // than silently logging the person in under their real role.
        $roleMap = ['teacher' => 'Teacher', 'parent' => 'Parent', 'admin' => 'Admin'];
        $submittedRole = $credentials['role'] ?? null;

        if ($submittedRole && ($roleMap[$submittedRole] ?? null) !== $user->user_type) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => "This account is registered as a {$user->user_type}. Please use the {$user->user_type} login instead.",
            ]);
        }

        // Account-level deactivation (Admin's "Deactivate" action) blocks
        // login outright, regardless of correct credentials.
        if ($user->status === 'Inactive') {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated. Contact your school\'s TaraBasa admin.',
            ]);
        }

        // A Rejected Teacher gets a clear, specific rejection message even
        // though their credentials are correct — per the Admin Actor Prompt.
        if ($user->user_type === 'Teacher' && $user->teacher && $user->teacher->status === 'Rejected') {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'Your teacher registration was not approved. Contact your school\'s TaraBasa admin.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return match ($user->user_type) {
            'Admin' => redirect()->route('admin.dashboard'),
            'Teacher' => redirect()->route('teacher.dashboard'),
            'Parent' => redirect()->route('parent.dashboard'),
        };
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
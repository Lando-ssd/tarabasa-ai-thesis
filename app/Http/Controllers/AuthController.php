<?php

namespace App\Http\Controllers;

use App\Models\ParentAccount;
use App\Models\Teacher;
use App\Models\User;
use App\Support\EmailAddress;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

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
        $this->normalizeEmailInput($request);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => $this->registrationEmailRules(),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'school_name' => ['required', 'string', 'max:255'],
            'employee_id' => ['required', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            ...Teacher::gradeRules(),
        ], $this->registrationEmailMessages());
        $grades = Teacher::gradesFromValidated($validated);

        $user = DB::transaction(function () use ($validated, $grades) {
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
                'grades_handled' => $grades,
            ]);

            return $user;
        });

        // With "confirm your email first" on (the default), nobody is signed in until they open the link, and an
        // address we cannot send to is refused instead of leaving an account nobody can ever use.
        if ($refused = $this->sendOrRefuse($user, $request)) {
            return $refused;
        }

        return $this->registrationDone($user, 'teacher');
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
        $this->normalizeEmailInput($request);

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => $this->registrationEmailRules(),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], $this->registrationEmailMessages());

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

        if ($refused = $this->sendOrRefuse($user, $request)) {
            return $refused;
        }

        return $this->registrationDone($user, 'parent');
    }

    private function mustConfirmEmail(): bool
    {
        return (bool) config('auth.require_verified_email', true);
    }

    /** Emails are compared and stored in lower case, so "Pinky@Gmail.com" and "pinky@gmail.com" are one address. */
    private function normalizeEmailInput(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => strtolower(trim($request->input('email')))]);
        }
    }

    /** What a registration email must satisfy: a real looking address, a domain that can receive mail, no obvious typo, not taken. */
    private function registrationEmailRules(): array
    {
        return [
            'required',
            // First, so a typo like "gmial.com" gets the helpful "Did you mean" instead of "does not look real".
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (is_string($value) && ($hint = EmailAddress::suggestion($value))) {
                    $fail("That address looks mistyped. Did you mean {$hint}?");
                }
            },
            config('auth.check_email_domain') ? 'email:rfc,dns' : 'email',
            'max:255',
            Rule::unique('users', 'email'),
        ];
    }

    private function registrationEmailMessages(): array
    {
        return [
            'email.email' => 'That email address does not look real. Check the spelling, or use another address.',
            'email.unique' => 'That email address already has an account. Log in instead, or use another address.',
        ];
    }

    /**
     * Sends the confirmation link. When confirming is required and the email cannot be sent, the account that was just
     * made is removed again and the form comes back with a plain message, so the address can be retyped. Returns the
     * response to send back when it was refused, or null when all is well.
     */
    private function sendOrRefuse(User $user, Request $request): mixed
    {
        if (! $this->mustConfirmEmail()) {
            $this->sendVerificationEmail($user);
            Auth::login($user);

            return null;
        }

        if ($this->sendVerificationEmail($user)) {
            return null;
        }

        DB::transaction(function () use ($user) {
            Teacher::where('user_id', $user->id)->delete();
            ParentAccount::where('user_id', $user->id)->delete();
            $user->delete();
        });

        return back()
            ->withInput($request->except(['password', 'password_confirmation']))
            ->withErrors(['email' => 'We could not send the confirmation email right now. Check the address is typed correctly and try again in a minute.']);
    }

    private function registrationDone(User $user, string $role)
    {
        return view('auth.registration-submitted', [
            'firstName' => $user->first_name,
            'email' => $user->email,
            'role' => $role,
            'mustConfirm' => $this->mustConfirmEmail(),
        ]);
    }

    /**
     * Sends the real confirmation email — but a registration must never
     * fail just because SMTP is slow or unreachable. Confirmed for real:
     * an unprotected send here could hang the whole request for ~60s and
     * then return a raw 500, turning a mail-server problem into a broken
     * registration. Every other external call in this app (Gemini,
     * Reading-api, Adaptive_Recommendator) already degrades gracefully
     * instead of crashing the user-facing flow — this brings registration
     * in line with that same pattern.
     */
    private function sendVerificationEmail(User $user): bool
    {
        try {
            $user->sendEmailVerificationNotification();

            return true;
        } catch (Throwable $e) {
            Log::warning('Verification email failed to send.', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * "Send the link again", for someone who cannot sign in yet so has no session to ask from. The answer is always
     * the same whether or not the address has an account, so this cannot be used to find out who is registered.
     */
    public function resendVerification(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $email = strtolower(trim($data['email']));

        $user = User::where('email', $email)->first();

        // One link a minute per address, whatever the answer was.
        if ($user && $user->user_type !== 'Admin' && ! $user->hasVerifiedEmail() && Cache::add('resend-verification:'.$user->id, true, 60)) {
            $this->sendVerificationEmail($user);
        }

        return redirect()->route('login')->with('status', 'If that address has an account that is still waiting to be confirmed, we sent a new link. Check your inbox and your spam folder.');
    }

    /**
     * Handles the actual click on the verification link in the email.
     *
     * The route's `signed` middleware checks the link is genuine and has not expired. The person does NOT have
     * to be signed in: people open the email on a phone, in another browser, or while another account (an
     * Admin) is signed in on the same computer, and a link that needs the matching session silently did
     * nothing for them (the old route needed `auth` plus a matching user, so the account stayed "Email not
     * verified" and the Admin could not approve it). The link carries the user's id and a hash of their email,
     * so a valid link can only ever verify the one account it was made for.
     */
    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->view('auth.verify-link-problem', [], 403);
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
            Log::info('Email address confirmed.', ['user_id' => $user->id]);
        }

        // A page of its own that names the address, not a quiet redirect: whoever opened the link, and
        // whoever is looking at the Admin screen, can see which account was confirmed.
        return view('auth.email-verified', [
            'email' => EmailAddress::mask($user->email),
            'role' => strtolower((string) $user->user_type),
            'isTeacher' => $user->user_type === 'Teacher',
        ]);
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
        // One log in for everyone. A learner code (like TB26-48293) typed in the first box, with the
        // 4 digit PIN in the second box, signs the child straight in and opens their own dashboard
        // (or the first reading check): nothing is typed twice. Same rules as the child's own page:
        // the same lock-out, the same message for a wrong code and a wrong PIN.
        // (A list or anything that is not text in the box is never a code: it falls through to the
        // email rules below and is refused there, instead of crashing.)
        $typed = $request->input('email');
        if (is_string($typed) && \App\Support\LearnerCode::looksLikeCode($typed)) {
            $request->validate(['password' => ['required', 'string']], ['password.required' => 'Type your 4 digit PIN.']);
            $request->merge(['learner_code' => $typed, 'pin' => (string) $request->input('password')]);

            return app(LearnerAuthController::class)->login($request, app(\App\Services\LearnerAuthService::class));
        }

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

        if ($blockedReason = $this->reasonLoginBlocked($user, $credentials['role'] ?? null)) {
            // Lets the sign in page offer "send the link again" right under the message.
            if ($this->needsConfirming($user)) {
                $request->session()->flash('unconfirmed_email', $user->email);
            } else {
                RateLimiter::hit($throttleKey, 60);
            }

            throw ValidationException::withMessages(['email' => $blockedReason]);
        }

        RateLimiter::clear($throttleKey);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return $this->redirectForUserType($user);
    }

    /**
     * The same eligibility rules (role match / active / not-rejected)
     * password login already enforced — pulled out so "Continue with
     * Google" checks the exact same rules instead of risking a second,
     * slightly-different copy. Returns a user-facing message when login
     * should be blocked, or null when it's fine to proceed.
     */
    private function reasonLoginBlocked(User $user, ?string $submittedRole): ?string
    {
        // The role picked on the login form (Teacher/Parent/Admin login
        // links, or the general "Log in" link with no role at all) must
        // match the account's real user_type. Correct credentials on the
        // wrong role's form are rejected with a specific message rather
        // than silently logging the person in under their real role.
        $roleMap = ['teacher' => 'Teacher', 'parent' => 'Parent', 'admin' => 'Admin'];

        if ($submittedRole && ($roleMap[$submittedRole] ?? null) !== $user->user_type) {
            return "This account is registered as a {$user->user_type}. Please use the {$user->user_type} login instead.";
        }

        // Account-level deactivation (Admin's "Deactivate" action) blocks
        // login outright, regardless of correct credentials.
        if ($user->status === 'Inactive') {
            return 'This account has been deactivated. Contact your school\'s TaraBasa admin.';
        }

        // A Rejected Teacher gets a clear, specific rejection message even
        // though their credentials are correct — per the Admin Actor Prompt.
        if ($user->user_type === 'Teacher' && $user->teacher && $user->teacher->status === 'Rejected') {
            // The Admin's reason, when they gave one, so the teacher knows what to fix or whom to show proof to.
            $reason = trim((string) $user->teacher->rejection_reason);

            return 'Your teacher registration was not approved.'.($reason !== '' ? ' Reason: '.rtrim($reason, '.').'.' : '').' Contact your school\'s TaraBasa admin.';
        }

        // The address must be confirmed first. Only said after the password was right, so it does not
        // tell a stranger which addresses have accounts.
        if ($this->needsConfirming($user)) {
            return 'Please confirm your email first. Open the link we sent to '.EmailAddress::mask($user->email).' (look in spam too), then log in. Tap the button under this message to get a new link.';
        }

        return null;
    }

    /** A Teacher or Parent whose email has not been confirmed, while confirming is required. (The Admin is never held back.) */
    private function needsConfirming(User $user): bool
    {
        return $this->mustConfirmEmail() && $user->user_type !== 'Admin' && ! $user->hasVerifiedEmail();
    }

    private function redirectForUserType(User $user)
    {
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

    /**
     * "Continue with Google" — step 1. Sends the browser to Google's own
     * consent screen. Google Sign-In only ever logs an EXISTING account in
     * here (see handleGoogleCallback) — it never creates one, since a
     * brand-new Google sign-in has no way to know which role (Teacher vs
     * Parent) the person is, and Teacher registration needs real
     * school-verification fields Google can't supply.
     */
    public function redirectToGoogle(Request $request)
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login', ['role' => $request->query('role')])
                ->withErrors(['email' => 'Google Sign-In isn\'t set up yet. Please log in with your email and password for now.']);
        }

        // Carried across the round trip to Google so the callback can
        // re-apply the exact same role-mismatch check the form submit does.
        $request->session()->put('google_login_role', $request->query('role'));

        return Socialite::driver('google')->redirect();
    }

    /**
     * "Continue with Google" — step 2, after Google sends the browser back.
     */
    public function handleGoogleCallback(Request $request)
    {
        $submittedRole = $request->session()->pull('google_login_role');

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            return redirect()->route('login', ['role' => $submittedRole])
                ->withErrors(['email' => 'Google Sign-In didn\'t go through. Please try again, or log in with your email and password.']);
        }

        // Only trust an email address Google itself says it has verified: signing in by email is
        // only safe if the person really owns that address.
        $verified = data_get($googleUser->user ?? [], 'email_verified', data_get($googleUser->user ?? [], 'verified_email'));
        if ($googleUser->getEmail() === null || $verified === false || $verified === 'false') {
            return redirect()->route('login', ['role' => $submittedRole])
                ->withErrors(['email' => 'Google could not confirm that email address. Please log in with your email and password.']);
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if (! $user) {
            return redirect()->route('login', ['role' => $submittedRole])
                ->withErrors(['email' => "No TaraBasa account found for {$googleUser->getEmail()}. Please register first."]);
        }

        // Google has just proved this person owns the address (checked above), which is exactly what the
        // confirmation email is for, so there is nothing left to confirm.
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        if ($blockedReason = $this->reasonLoginBlocked($user, $submittedRole)) {
            return redirect()->route('login', ['role' => $submittedRole])
                ->withErrors(['email' => $blockedReason]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return $this->redirectForUserType($user);
    }
}
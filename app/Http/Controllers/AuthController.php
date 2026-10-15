<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AccountAuthService;
use App\Services\AccountLoginRefused;
use App\Support\EmailAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AuthController extends Controller
{
    /** The sign in and sign up rules live in AccountAuthService, shared with the phone app's API. */
    public function __construct(private readonly AccountAuthService $accounts) {}

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
     * Creates the account and sends the real confirmation email. With "confirm your email first" on (the default)
     * nobody is signed in until they open the link; with it off, the Teacher is signed in at once with limited access
     * (per the Admin Actor Prompt: "a Pending Teacher can already log in to a locked-down dashboard"). Admin
     * approval is a separate gate that happens later, not something this step waits on.
     */
    public function storeTeacher(Request $request)
    {
        $this->normalizeEmailInput($request);

        $validated = $request->validate($this->accounts->teacherRules(), $this->accounts->registrationEmailMessages());
        $user = $this->accounts->registerTeacher($validated);

        // An address we cannot send to is refused instead of leaving an account nobody can ever use.
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
     */
    public function storeParent(Request $request)
    {
        $this->normalizeEmailInput($request);

        $validated = $request->validate($this->accounts->parentRules(), $this->accounts->registrationEmailMessages());
        $user = $this->accounts->registerParent($validated);

        if ($refused = $this->sendOrRefuse($user, $request)) {
            return $refused;
        }

        return $this->registrationDone($user, 'parent');
    }

    private function normalizeEmailInput(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => $this->accounts->normalizeEmail($request->input('email'))]);
        }
    }

    /**
     * Sends the confirmation link. When confirming is required and the email cannot be sent, the account that was just
     * made is removed again and the form comes back with a plain message, so the address can be retyped. Returns the
     * response to send back when it was refused, or null when all is well.
     */
    private function sendOrRefuse(User $user, Request $request): mixed
    {
        if (! $this->accounts->mustConfirmEmail()) {
            $this->accounts->sendVerificationEmail($user);
            Auth::login($user);

            return null;
        }

        if ($this->accounts->sendVerificationEmail($user)) {
            return null;
        }

        $this->accounts->discard($user);

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
            'mustConfirm' => $this->accounts->mustConfirmEmail(),
        ]);
    }

    /**
     * "Send the link again", for someone who cannot sign in yet so has no session to ask from. The answer is always
     * the same whether or not the address has an account, so this cannot be used to find out who is registered.
     */
    public function resendVerification(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $this->accounts->resendVerification($data['email']);

        return redirect()->route('login')->with('status', 'If that address has an account that is still waiting to be confirmed, we sent a new link. Check your inbox and your spam folder.');
    }

    /**
     * Handles the actual click on the verification link in the email.
     *
     * The route's `signed` middleware checks the link is genuine and has not expired. The person does NOT have
     * to be signed in: people open the email on a phone, in another browser, or while another account (an
     * Admin) is signed in on the same computer, and a link that needs the matching session silently did
     * nothing for them. The link carries the user's id and a hash of their email, so a valid link can only
     * ever verify the one account it was made for.
     */
    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = $this->accounts->confirmFromLink($id, $hash);

        if (! $user) {
            return response()->view('auth.verify-link-problem', [], 403);
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

        try {
            $user = $this->accounts->authenticate($credentials['email'], $credentials['password'], (string) $request->ip(), $credentials['role'] ?? null);
        } catch (AccountLoginRefused $refused) {
            // Lets the sign in page offer "send the link again" right under the message.
            if ($refused->reason === 'email_not_confirmed') {
                $request->session()->flash('unconfirmed_email', $refused->email);
            }

            throw ValidationException::withMessages(['email' => $refused->getMessage()]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return $this->redirectForUserType($user);
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

        if ($blockedReason = $this->accounts->reasonLoginBlocked($user, $submittedRole)) {
            return redirect()->route('login', ['role' => $submittedRole])
                ->withErrors(['email' => $blockedReason]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return $this->redirectForUserType($user);
    }
}
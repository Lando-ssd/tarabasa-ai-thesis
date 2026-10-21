<?php

namespace App\Services;

use App\Models\ParentAccount;
use App\Models\Teacher;
use App\Models\User;
use App\Support\EmailAddress;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Sign in and sign up for Teacher and Parent accounts, in ONE place. The website's AuthController and the phone
 * app's AccountApiController both call this, so the rules (what an email must satisfy, who may sign in, the lock-out,
 * the confirmation email) cannot drift into two different answers. Nothing here knows about sessions or tokens: the
 * caller decides how a signed in person is remembered (a cookie on the website, a bearer token on the phone).
 */
class AccountAuthService
{
    /** Five wrong passwords for one address from one place, then a minute of waiting (the same limiter the website always used). */
    private const MAX_ATTEMPTS = 5;

    public function mustConfirmEmail(): bool
    {
        return (bool) config('auth.require_verified_email', true);
    }

    /** Emails are compared and stored in lower case, so "Pinky@Gmail.com" and "pinky@gmail.com" are one address. */
    public function normalizeEmail(mixed $email): mixed
    {
        return is_string($email) ? strtolower(trim($email)) : $email;
    }

    /** What a registration email must satisfy: a real looking address, a domain that can receive mail, no obvious typo, not taken. */
    public function registrationEmailRules(): array
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

    public function registrationEmailMessages(): array
    {
        return [
            ...ConsentService::messages(),
            'email.email' => 'That email address does not look real. Check the spelling, or use another address.',
            'email.unique' => 'That email address already has an account. Log in instead, or use another address.',
        ];
    }

    public function teacherRules(bool $requireConsent = true): array
    {
        return [
            'accepts_terms' => ConsentService::termsRule($requireConsent),
            'first_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => $this->registrationEmailRules(),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'school_name' => ['required', 'string', 'max:255'],
            'employee_id' => ['required', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:30'],
            ...Teacher::gradeRules(),
        ];
    }

    public function parentRules(bool $requireConsent = true): array
    {
        return [
            'accepts_terms' => ConsentService::termsRule($requireConsent),
            'first_name' => ['required', 'string', 'max:255'],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => $this->registrationEmailRules(),
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /** Creates the Teacher (Pending, 2 free generation credits). The caller already validated with teacherRules(). */
    public function registerTeacher(array $validated, string $channel = 'web'): User
    {
        $grades = Teacher::gradesFromValidated($validated);

        return DB::transaction(function () use ($validated, $grades, $channel) {
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

            ConsentService::record($user, ConsentService::TERMS_PRIVACY, $validated['accepts_terms'] ?? false, null, $channel);

            return $user;
        });
    }

    public function registerParent(array $validated, string $channel = 'web'): User
    {
        return DB::transaction(function () use ($validated, $channel) {
            $user = User::create([
                'first_name' => $validated['first_name'],
                'middle_initial' => $validated['middle_initial'] ?? null,
                'last_name' => $validated['last_name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'user_type' => 'Parent',
            ]);

            ParentAccount::create(['user_id' => $user->id]);

            ConsentService::record($user, ConsentService::TERMS_PRIVACY, $validated['accepts_terms'] ?? false, null, $channel);

            return $user;
        });
    }

    /**
     * Sends the real confirmation email, but a registration must never fail just because the mail server is slow or
     * unreachable (an unprotected send once hung a whole request for a minute and ended in a raw 500). True when it
     * was sent.
     */
    public function sendVerificationEmail(User $user): bool
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

    /** Removes an account whose confirmation email could not be sent, so nothing unusable is left behind. */
    public function discard(User $user): void
    {
        DB::transaction(function () use ($user) {
            Teacher::where('user_id', $user->id)->delete();
            ParentAccount::where('user_id', $user->id)->delete();
            DB::table('consent_records')->where('user_id', $user->id)->delete();
            $user->delete();
        });
    }

    /**
     * "Send the link again" for someone who cannot sign in yet. The caller always answers the same way whether or
     * not the address has an account, so this cannot be used to find out who is registered. One link a minute per
     * account, never for an Admin or an address that is already confirmed.
     */
    public function resendVerification(string $email): void
    {
        $user = User::where('email', $this->normalizeEmail($email))->first();

        if ($user && $user->user_type !== 'Admin' && ! $user->hasVerifiedEmail() && Cache::add('resend-verification:'.$user->id, true, 60)) {
            $this->sendVerificationEmail($user);
        }
    }

    /**
     * Opens the link in the confirmation email. The link was already checked to be genuine and unexpired (the route's
     * `signed` middleware); this checks the hash belongs to THAT account and confirms it. Null when the link does not
     * match an account.
     */
    public function confirmFromLink(int $id, string $hash): ?User
    {
        $user = User::find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return null;
        }

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
            Log::info('Email address confirmed.', ['user_id' => $user->id]);
        }

        return $user;
    }

    /**
     * Checks an email and password and decides whether this person may sign in. Same answers everywhere: the same
     * text for an unknown address and a wrong password (never says which), the lock-out after five wrong tries, and
     * the other reasons (wrong role form, deactivated, rejected, email not confirmed) only AFTER the password was
     * right, so a stranger cannot learn which addresses have accounts.
     *
     * @throws AccountLoginRefused
     */
    public function authenticate(string $email, string $password, string $ip, ?string $submittedRole = null): User
    {
        $email = (string) $this->normalizeEmail($email);
        $throttleKey = $email.'|'.$ip;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw new AccountLoginRefused("Too many login attempts. Please try again in {$seconds} seconds.", 'too_many_attempts');
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($throttleKey, 60);

            throw new AccountLoginRefused('Invalid email or password.', 'invalid_credentials');
        }

        if ($blocked = $this->reasonLoginBlocked($user, $submittedRole)) {
            // An unconfirmed address is not a wrong try: the person just needs the link, so it does not count toward the lock-out.
            if (! $this->needsConfirming($user)) {
                RateLimiter::hit($throttleKey, 60);
            }

            throw new AccountLoginRefused($blocked, $this->needsConfirming($user) ? 'email_not_confirmed' : 'not_allowed', $user->email);
        }

        RateLimiter::clear($throttleKey);

        return $user;
    }

    /**
     * The eligibility rules (role match, active, not rejected, email confirmed). Returns a message when sign in should
     * be refused, or null. Also used by "Continue with Google" so it checks the exact same rules.
     */
    public function reasonLoginBlocked(User $user, ?string $submittedRole): ?string
    {
        // The role picked on the sign in form (Teacher, Parent or Admin link, or none at all) must match the account's
        // real type. Correct credentials on the wrong role's form are refused with a specific message rather than
        // silently signing the person in under their real role.
        $roleMap = ['teacher' => 'Teacher', 'parent' => 'Parent', 'admin' => 'Admin'];

        if ($submittedRole && ($roleMap[$submittedRole] ?? null) !== $user->user_type) {
            return "This account is registered as a {$user->user_type}. Please use the {$user->user_type} login instead.";
        }

        // Account-level deactivation (the Admin's "Deactivate") blocks sign in outright.
        if ($user->status === 'Inactive') {
            return 'This account has been deactivated. Contact your school\'s TaraBasa admin.';
        }

        // A Rejected Teacher gets a clear, specific message even though the credentials are correct.
        if ($user->user_type === 'Teacher' && $user->teacher && $user->teacher->status === 'Rejected') {
            // The Admin's reason, when they gave one, so the teacher knows what to fix or whom to show proof to.
            $reason = trim((string) $user->teacher->rejection_reason);

            return 'Your teacher registration was not approved.'.($reason !== '' ? ' Reason: '.rtrim($reason, '.').'.' : '').' Contact your school\'s TaraBasa admin.';
        }

        // The address must be confirmed first.
        if ($this->needsConfirming($user)) {
            return 'Please confirm your email first. Open the link we sent to '.EmailAddress::mask($user->email).' (look in spam too), then log in. Tap the button under this message to get a new link.';
        }

        return null;
    }

    /** A Teacher or Parent whose email has not been confirmed, while confirming is required. (The Admin is never held back.) */
    public function needsConfirming(User $user): bool
    {
        return $this->mustConfirmEmail() && $user->user_type !== 'Admin' && ! $user->hasVerifiedEmail();
    }
}

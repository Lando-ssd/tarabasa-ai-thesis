<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccountAuthService;
use App\Services\AccountLoginRefused;
use App\Support\EmailAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The phone app's sign in and sign up for Teachers and Parents. Thin JSON wrappers over AccountAuthService, the same
 * code the website's AuthController calls, so the two cannot disagree about who may sign in or what an email must
 * look like. A Learner signs in separately (LearnerApiController, code and PIN). An Admin is never accepted here:
 * Admin screens are on the website only.
 */
class AccountApiController extends Controller
{
    public function __construct(private readonly AccountAuthService $accounts) {}

    /** Email and password in, a bearer token and who they are out. Refuses with the website's own wording. */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        try {
            $user = $this->accounts->authenticate($credentials['email'], $credentials['password'], (string) $request->ip());
        } catch (AccountLoginRefused $refused) {
            return $this->refused($refused);
        }

        // The right password for an Admin is still a no: their screens are on the website only. Said only after the
        // password was right, like every other refusal, so it does not tell a stranger which addresses are Admins.
        if ($user->user_type === 'Admin') {
            return response()->json(['error' => 'admin_web_only', 'message' => 'Admin accounts sign in on the website.'], 403);
        }

        return response()->json([
            'token' => $user->createToken('mobile')->plainTextToken,
            'account' => $this->accountPayload($user),
        ]);
    }

    /**
     * Teacher sign up. Same rules as the website. With "confirm your email first" on (the default) there is no token
     * yet: the person opens the link in the email, then signs in.
     */
    public function registerTeacher(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);
        $validated = $request->validate($this->accounts->teacherRules(), $this->accounts->registrationEmailMessages());

        return $this->registered($this->accounts->registerTeacher($validated));
    }

    public function registerParent(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);
        $validated = $request->validate($this->accounts->parentRules(), $this->accounts->registrationEmailMessages());

        return $this->registered($this->accounts->registerParent($validated));
    }

    /** "Send the link again". The answer is the same whether or not the address has an account. */
    public function resendVerification(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $this->accounts->resendVerification($data['email']);

        return response()->json(['message' => 'If that address has an account that is still waiting to be confirmed, we sent a new link. Check your inbox and your spam folder.']);
    }

    /** "I forgot my password". Same wording and the same limit as the website, and it never says whether the address exists. */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email', 'max:255']]);

        $throttleKey = 'forgot-password|'.strtolower($request->email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages(['email' => "Too many requests. Please try again in {$seconds} seconds."]);
        }
        RateLimiter::hit($throttleKey, 300);

        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'If an account exists for that email, a reset link is on its way.']);
    }

    /** Who this token belongs to, so the app can open the right screens after it starts again. */
    public function me(Request $request): JsonResponse
    {
        return response()->json(['account' => $this->accountPayload($request->user())]);
    }

    /** Deletes only this device's token. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['ok' => true]);
    }

    private function registered(User $user): JsonResponse
    {
        if (! $this->accounts->mustConfirmEmail()) {
            $this->accounts->sendVerificationEmail($user);

            return response()->json([
                'status' => 'signed_in',
                'token' => $user->createToken('mobile')->plainTextToken,
                'account' => $this->accountPayload($user),
            ], 201);
        }

        // An address we cannot send to is refused instead of leaving an account nobody can ever use.
        if (! $this->accounts->sendVerificationEmail($user)) {
            $this->accounts->discard($user);

            throw ValidationException::withMessages(['email' => 'We could not send the confirmation email right now. Check the address is typed correctly and try again in a minute.']);
        }

        return response()->json([
            'status' => 'confirm_email',
            'email' => $user->email,
            'maskedEmail' => EmailAddress::mask($user->email),
            'firstName' => $user->first_name,
            'role' => strtolower((string) $user->user_type),
        ], 201);
    }

    private function refused(AccountLoginRefused $refused): JsonResponse
    {
        $status = match ($refused->reason) {
            'invalid_credentials' => 401,
            'too_many_attempts' => 429,
            default => 403,
        };

        return response()->json([
            'error' => $refused->reason,
            'message' => $refused->getMessage(),
            // Lets the app offer "send the link again" right under the message.
            'email' => $refused->reason === 'email_not_confirmed' ? $refused->email : null,
        ], $status);
    }

    private function normalizeEmail(Request $request): void
    {
        if (is_string($request->input('email'))) {
            $request->merge(['email' => $this->accounts->normalizeEmail($request->input('email'))]);
        }
    }

    /** What the app may know about an account: never the password hash, tokens or anything internal. */
    private function accountPayload(User $user): array
    {
        $base = [
            'id' => $user->id,
            'role' => strtolower((string) $user->user_type),
            'firstName' => $user->first_name,
            'middleInitial' => $user->middle_initial,
            'lastName' => $user->last_name,
            'email' => $user->email,
            'contactNumber' => $user->contact_number,
        ];

        if ($user->user_type === 'Teacher') {
            $base['teacher'] = self::teacherPayload($user->teacher);
        }

        return $base;
    }

    /**
     * The Teacher part of the account the phone holds: status, school, the grades they may open classes for, any request for more grades
     * that is waiting for the Admin, and the Admin's latest answer. One place, so sign in, /me and the profile screens cannot disagree.
     */
    public static function teacherPayload(?\App\Models\Teacher $teacher): array
    {
        return [
            'status' => $teacher?->status,
            'schoolName' => $teacher?->school_name,
            'grades' => $teacher ? $teacher->gradesAllowed() : [],
            'gradesPending' => $teacher?->gradeRequestPayload(),
            'gradesDecision' => $teacher?->latestGradeDecision(),
            'freeCredits' => (int) ($teacher?->free_generation_credits_remaining ?? 0),
        ];
    }
}

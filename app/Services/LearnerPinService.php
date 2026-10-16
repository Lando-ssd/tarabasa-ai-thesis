<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\LearnerPinChange;
use App\Models\Notification;
use App\Models\User;
use App\Support\PinRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * A guardian sets a new PIN for a child who forgot theirs. The ONE place this is decided, so the website and the phone app cannot
 * disagree.
 *
 * Why a guardian and not the child: a child has no email and no other way to prove who they are, and a brother or a classmate could
 * reset a PIN they do not know. A guardian is already signed in, and the child is already linked to them.
 *
 * The limits (each is enforced here, on the server, never only on the screen):
 *   1. Only a guardian linked to this child (anyone else gets "not found", not "not allowed", so a code cannot be probed).
 *   2. The guardian's own account password again, because a phone left open or a child holding the parent's phone must not be enough.
 *      Five wrong passwords lock this for 15 minutes.
 *   3. At most PinRules::CHANGES_PER_DAY changes for one child in 24 hours.
 *   4. A PIN they choose may not be four of the same number or a straight run, and may not be the PIN it replaces (PinRules).
 *   5. The old PIN stops working at once, the child is signed out everywhere (phone sign ins and website sessions), and their count of
 *      wrong tries starts again.
 *   6. The other guardians are told. Nothing here ever stores or logs a PIN: only who changed it, how and when.
 */
class LearnerPinService
{
    public const PASSWORD_ATTEMPTS = 5;

    /**
     * @param  string  $mode  'generate' (the system makes one) or 'choose' (the guardian types one)
     * @return array{pin: ?string, message: string}  `pin` is only set for 'generate': it is shown once and is not kept anywhere readable
     *
     * @throws ValidationException with the message under `password` or `pin`
     */
    public function reset(User $user, Learner $learner, string $password, string $mode, ?string $pin = null): array
    {
        $parent = $user->parentProfile;
        abort_if(! $parent || ! $parent->learners()->where('learners.id', $learner->id)->exists(), 404);

        $passwordKey = 'pin-reset-password|user:'.$user->id;

        if (RateLimiter::tooManyAttempts($passwordKey, self::PASSWORD_ATTEMPTS)) {
            $minutes = (int) ceil(RateLimiter::availableIn($passwordKey) / 60);

            throw ValidationException::withMessages(['password' => "Too many tries. Please wait {$minutes} ".($minutes === 1 ? 'minute' : 'minutes').' and try again.']);
        }

        if (! Hash::check($password, $user->password)) {
            RateLimiter::hit($passwordKey, 900);

            throw ValidationException::withMessages(['password' => 'That password is not right.']);
        }

        RateLimiter::clear($passwordKey);

        $recent = LearnerPinChange::where('learner_id', $learner->id)->where('changed_at', '>=', now()->subDay())->orderBy('changed_at')->get();

        if ($recent->count() >= PinRules::CHANGES_PER_DAY) {
            $hours = max(1, (int) ceil(now()->diffInMinutes($recent->first()->changed_at->copy()->addDay()) / 60));

            throw ValidationException::withMessages(['pin' => "{$learner->first_name}'s PIN was already changed ".PinRules::CHANGES_PER_DAY." times today. For safety it can be changed again in about {$hours} ".($hours === 1 ? 'hour' : 'hours').'.']);
        }

        if ($mode === 'generate') {
            $new = PinRules::generate($learner->pin);
            $shown = $new;
        } else {
            if ($refusal = PinRules::refusal((string) $pin, $learner->pin)) {
                throw ValidationException::withMessages(['pin' => $refusal]);
            }

            $new = (string) $pin;
            $shown = null;
        }

        DB::transaction(function () use ($learner, $user, $new, $mode) {
            $learner->update(['pin' => Hash::make($new)]);

            LearnerPinChange::create(['learner_id' => $learner->id, 'user_id' => $user->id, 'method' => $mode === 'generate' ? 'generated' : 'chosen', 'changed_at' => now()]);
        });

        // Signed in on a phone with the old PIN? That ends now. (A website session ends at its next page: see stamp().)
        $learner->tokens()->delete();

        Notification::notifyForLearner(
            $learner,
            Notification::TYPE_PIN_CHANGED,
            "{$user->first_name} {$user->last_name} set a new PIN for {$learner->first_name}.",
            includeTeacher: false,
            exceptUserId: $user->id,
        );

        return ['pin' => $shown, 'message' => "{$learner->first_name}'s PIN was changed. The old PIN no longer works."];
    }

    /** The fields of the "new PIN" form, the same on the website and the phone. */
    public static function formRules(): array
    {
        return [
            'mode' => ['required', \Illuminate\Validation\Rule::in(['generate', 'choose'])],
            'password' => ['required', 'string'],
            'pin' => ['required_if:mode,choose', 'nullable', 'digits:4'],
            'pin_confirmation' => ['required_if:mode,choose', 'nullable', 'same:pin'],
        ];
    }

    /** @return array<string, string> */
    public static function formMessages(): array
    {
        return [
            'mode.required' => 'Choose how to make the new PIN.',
            'password.required' => 'Type your password, so we know it is you.',
            'pin.required_if' => 'Type the new 4 digit PIN.',
            'pin.digits' => 'The PIN must be exactly 4 numbers.',
            'pin_confirmation.required_if' => 'Type the new PIN again.',
            'pin_confirmation.same' => 'The two PINs are not the same.',
        ];
    }

    /**
     * A short fingerprint of the child's current PIN ("id:fingerprint", so it belongs to ONE child). A website sign in remembers it, and EnsureLearnerLoggedIn signs the child out as
     * soon as it no longer matches (the PIN was changed). It cannot be turned back into the PIN.
     */
    public static function stamp(Learner $learner): string
    {
        return $learner->id.':'.substr(hash('sha256', (string) $learner->pin), 0, 16);
    }
}

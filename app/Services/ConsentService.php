<?php

namespace App\Services;

use App\Models\ConsentRecord;
use App\Models\Learner;
use App\Models\User;

/**
 * Writes down what a person agreed to. The boxes live on the sign up forms, in the "Add your child" steps and on the link
 * page (and in the phone app), the wording lives on the legal pages (resources/views/legal), and this is the one place a
 * tick becomes a record, so the website and the phone app cannot keep two different kinds of evidence.
 *
 * A record is only written when the person really ticked the box. On the website the box is required (the form is refused
 * without it); the phone app sends it from build 0.5.5, and until then the server accepts a sign up without it unless
 * config('legal.api_consent_required') is on.
 */
class ConsentService
{
    public const TERMS_PRIVACY = 'terms_privacy';

    public const CHILD_DATA = 'child_data';

    public const CHILD_PHOTO = 'child_photo';

    public const CHILD_LINK = 'child_link';

    /** Keeps what was agreed to, for this person (and child). Returns nothing when the box was not ticked. */
    public static function record(User $user, string $kind, mixed $ticked, ?Learner $learner = null, string $channel = 'web'): ?ConsentRecord
    {
        if (! filter_var($ticked, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        return ConsentRecord::create([
            'user_id' => $user->id,
            'learner_id' => $learner?->id,
            'kind' => $kind,
            'policy_version' => (string) config('legal.version'),
            'channel' => $channel === 'app' ? 'app' : 'web',
            'accepted_at' => now(),
        ]);
    }

    /** The validation rule for an adult's "I agree" box when signing up. */
    public static function termsRule(bool $required = true): array
    {
        return $required ? ['accepted'] : ['nullable', 'boolean'];
    }

    public static function messages(): array
    {
        return [
            'accepts_terms.accepted' => 'Please tick the box to agree, or we cannot make your account.',
            'parent_consent.accepted' => "Please tick the box to confirm you are this child's parent or guardian and you agree, or we cannot add the child.",
            'photo_consent.accepted' => 'Please tick the photo box, or take the photo off.',
            'guardian_confirm.accepted' => "Please tick the box to confirm you are this child's parent or guardian.",
        ];
    }
}

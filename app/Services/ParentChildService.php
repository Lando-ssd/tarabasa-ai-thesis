<?php

namespace App\Services;

use App\Http\Controllers\LearnerController;
use App\Models\Learner;
use App\Models\Notification;
use App\Models\User;
use App\Support\LearnerCode;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A Parent adding a child, or linking to one who already has an account (Parent Actor Prompt Steps 4 and 5). The website's
 * LearnerController and the phone app's Api\ParentApiController both call this, so the questions, the way the starting level is
 * worked out, the limits on wrong PIN guesses and the notice to the other guardians cannot drift apart.
 */
class ParentChildService
{
    /**
     * Letters (any language), spaces, hyphens and apostrophes only, so "Anne-Marie" and "O'Brien" work but a name never has a digit.
     * \p{L} rather than a plain a-z so Filipino names with accented letters are not refused.
     */
    private const NAME_REGEX = '/^[\p{L}\s\'\-]+$/u';

    /** How the Parent describes the child's reading today, in the order the wizard shows them. */
    public const READING_STAGES = [
        'starting' => 'Cannot yet recognize letters',
        'letters' => 'Recognizes letters but cannot read words yet',
        'blending' => 'Reads simple words',
        'sentences' => 'Reads short sentences',
        'independent' => 'Reads grade-level passages on their own',
        'unsure' => 'Not sure',
    ];

    /** What the phone app's Add a child wizard needs: every list the questions are chosen from, with the wording the website shows. */
    public function options(): array
    {
        return [
            'grades' => ['Grade 1', 'Grade 2', 'Grade 3'],
            'avatars' => LearnerController::AVATARS,
            'homeLanguages' => LearnerController::HOME_LANGUAGES,
            'readingStages' => collect(self::READING_STAGES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'supports' => collect(LearnerController::SUPPORTS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'interests' => collect(LearnerController::INTERESTS)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'placementQuestions' => LearnerController::PLACEMENT_QUESTIONS,
        ];
    }

    /** @return array<string, mixed> */
    public function createRules(bool $requireConsent = true, bool $hasPhoto = false): array
    {
        return [
            // The parent's consent for the child's information (the Parent and Guardian Consent notice). The photo has its own box,
            // needed only when a photo is sent. Without a ticked box nothing about the child is kept.
            'parent_consent' => ConsentService::termsRule($requireConsent),
            'photo_consent' => $hasPhoto ? ['accepted'] : ['nullable', 'boolean'],
            'first_name' => ['required', 'string', 'max:100', 'regex:'.self::NAME_REGEX],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100', 'regex:'.self::NAME_REGEX],
            'grade_level' => ['required', Rule::in(['Grade 1', 'Grade 2', 'Grade 3'])],
            'avatar_id' => ['required', Rule::in(LearnerController::AVATARS)],
            'theme_color' => ['nullable', Rule::in(['blue', 'pink'])],
            // A cropped photo (if the Parent chose one instead of a character) arrives as a real file. Laravel's 'image' rule checks
            // the real content, not the file name.
            'avatar_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'reading_stage' => ['required', Rule::in(array_keys(self::READING_STAGES))],
            // Learning style is no longer asked: the research does not support teaching to a style (Pashler et al., 2008).
            'home_language' => ['nullable', Rule::in(LearnerController::HOME_LANGUAGES)],
            'supports' => ['nullable', 'array'],
            'supports.*' => ['string', Rule::in(array_keys(LearnerController::SUPPORTS))],
            'interests' => ['nullable', 'array'],
            'interests.*' => ['string', Rule::in(array_keys(LearnerController::INTERESTS))],
            // "Not sure" is 'unsure': no signal, never counted as a wrong answer.
            'q1' => ['required', Rule::in(['yes', 'no', 'unsure'])],
            'q2' => ['required', Rule::in(['yes', 'no', 'unsure'])],
            'q3' => ['required', Rule::in(['yes', 'no', 'unsure'])],
            'pin' => ['required', 'digits:4'],
            'pin_confirmation' => ['required', 'same:pin'],
        ];
    }

    /** @return array<string, string> */
    public function createMessages(): array
    {
        return [
            ...ConsentService::messages(),
            'first_name.regex' => 'First name may only contain letters, spaces, hyphens, and apostrophes — no numbers.',
            'last_name.regex' => 'Last name may only contain letters, spaces, hyphens, and apostrophes — no numbers.',
            'avatar_photo.image' => 'That file doesn\'t look like a real image.',
            'avatar_photo.mimes' => 'Photos must be a JPG, PNG, or WEBP file.',
            'avatar_photo.max' => 'Photos must be 2MB or smaller.',
        ];
    }

    /**
     * Makes the child and links the Parent as the one who created them.
     *
     * The placement score, not the self-report, sets the starting level (Step 4.4). It is worked out here, on the server, and never
     * trusted from the client. The raw answers are kept as given, so the first reading check can start where the Parent said the
     * child is instead of at a neutral default.
     *
     * @param  array<string, mixed>  $validated  already checked with createRules()
     */
    public function create(User $user, array $validated, ?UploadedFile $photo = null, string $channel = 'web'): Learner
    {
        $answers = collect([$validated['q1'], $validated['q2'], $validated['q3']]);
        $yesCount = $answers->filter(fn ($answer) => $answer === 'yes')->count();
        $answeredCount = $answers->filter(fn ($answer) => $answer !== 'unsure')->count();

        // Only the answered questions count. If every answer was "Not sure" there is no signal, so the starting level is the neutral
        // one and the first reading check decides.
        $masteryLevel = match (true) {
            $answeredCount === 0 => 'Developing',
            $yesCount >= 3 => 'Proficient',
            $yesCount === 2 => 'Developing',
            default => 'Beginning',
        };

        $parent = $user->parentProfile;

        // Stored under a random, unguessable file name (not the code or the name), so the path itself tells nobody who the child is.
        $photoPath = $photo ? $photo->store('avatars', 'public') : null;

        $placementAnswers = ['q1' => $validated['q1'], 'q2' => $validated['q2'], 'q3' => $validated['q3']];

        return $this->withFreshCode(function () use ($validated, $masteryLevel, $parent, $photoPath, $placementAnswers, $user, $channel) {
            $learner = Learner::create([
                'learner_code' => Learner::generateUniqueCode(),
                'class_id' => null,
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'grade_level' => $validated['grade_level'],
                'pin' => Hash::make($validated['pin']),
                'avatar_id' => $validated['avatar_id'],
                'theme_color' => $validated['theme_color'] ?? 'blue',
                'avatar_photo_path' => $photoPath,
                'mastery_level' => $masteryLevel,
                'home_language' => $validated['home_language'] ?? null,
                'supports' => array_values($validated['supports'] ?? []),
                'interests' => array_values($validated['interests'] ?? []),
                'reading_stage' => $validated['reading_stage'],
                'placement_answers' => $placementAnswers,
                'status' => 'Active',
            ]);

            $parent->learners()->attach($learner->id, [
                'relationship' => null,
                'is_creator' => true,
                'linked_at' => now(),
            ]);

            // What the parent agreed to, kept with the child: the child's information, and (separately) the photo if there is one.
            ConsentService::record($user, ConsentService::CHILD_DATA, $validated['parent_consent'] ?? false, $learner, $channel);
            if ($photoPath) {
                ConsentService::record($user, ConsentService::CHILD_PHOTO, $validated['photo_consent'] ?? false, $learner, $channel);
            }

            return $learner;
        });
    }

    /**
     * Runs the creation of a child, drawing a new learner code again if two parents happened to draw the same one at the same moment
     * (the database refuses the second). Only that clash is retried.
     */
    private function withFreshCode(callable $create): Learner
    {
        for ($try = 1; ; $try++) {
            try {
                return DB::transaction($create);
            } catch (UniqueConstraintViolationException $e) {
                if ($try >= 3 || ! str_contains($e->getMessage(), 'learner_code')) {
                    throw $e;
                }
            }
        }
    }

    /**
     * A second guardian joining a child someone else already made. Linking is the way someone gains access to a child's name, progress
     * and alerts, so it takes BOTH the code and the PIN (which the child's other guardian can give), and a wrong guess is limited per
     * parent and per code. A learner code has only about ten thousand possibilities a year, which is nothing to someone trying them all.
     * One message whether the code is unknown or the PIN is wrong, so it never says which. The guardians already linked are told.
     *
     * @throws ValidationException with the message under `learner_code`
     */
    public function link(User $user, string $rawCode, string $pin, string $relationship, mixed $confirmedGuardian = false, string $channel = 'web'): Learner
    {
        $parent = $user->parentProfile;
        $code = LearnerCode::normalize($rawCode);

        $parentKey = 'link-child|parent:'.$parent->id;
        $codeKey = 'link-child|code:'.$code;

        if (RateLimiter::tooManyAttempts($parentKey, 5) || RateLimiter::tooManyAttempts($codeKey, 5)) {
            $minutes = (int) ceil(max(RateLimiter::availableIn($parentKey), RateLimiter::availableIn($codeKey)) / 60);

            throw ValidationException::withMessages(['learner_code' => "Too many tries. Please wait {$minutes} ".($minutes === 1 ? 'minute' : 'minutes').' and try again.']);
        }

        $learner = Learner::where('learner_code', $code)->first();

        if (! $learner || ! Hash::check($pin, $learner->pin)) {
            RateLimiter::hit($parentKey, 900);
            RateLimiter::hit($codeKey, 900);

            throw ValidationException::withMessages(['learner_code' => 'That learner code and PIN do not match. Ask the child\'s other guardian for both.']);
        }

        if ($parent->learners()->where('learners.id', $learner->id)->exists()) {
            throw ValidationException::withMessages(['learner_code' => 'This child is already linked to your account.']);
        }

        RateLimiter::clear($parentKey);
        RateLimiter::clear($codeKey);

        // Tell the guardians who are already linked, so nobody can be added without them knowing.
        Notification::notifyForLearner(
            $learner,
            Notification::TYPE_GUARDIAN_LINKED,
            "{$user->first_name} {$user->last_name} was linked to {$learner->first_name} as {$relationship}.",
            includeTeacher: false
        );

        $parent->learners()->attach($learner->id, [
            'relationship' => $relationship,
            'is_creator' => false,
            'linked_at' => now(),
        ]);

        // The linking guardian confirms they are the child's parent or guardian (a record, so it can be shown later).
        ConsentService::record($user, ConsentService::CHILD_LINK, $confirmedGuardian, $learner, $channel);

        return $learner;
    }
}

<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
use App\Models\UserPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * A Teacher's or Parent's own profile: their name and contact number, their password, and (for a Teacher) the grades they handle.
 * The website's ProfileController and the phone app's Api\ProfileApiController both call this.
 *
 * Two things are deliberately NOT editable here, as on the website: the email (it is the login identity, and a new one would need its own
 * confirmation) and a Teacher's school and employee ID (the Admin verified them, and changing them afterwards would undermine that check).
 */
class AccountProfileService
{
    private const NAME_REGEX = '/^[\p{L}\s\'\-]+$/u';

    public function profileRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255', 'regex:'.self::NAME_REGEX],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:255', 'regex:'.self::NAME_REGEX],
            'contact_number' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function profileMessages(): array
    {
        return [
            'first_name.regex' => 'First name may only contain letters, spaces, hyphens, and apostrophes, no numbers.',
            'last_name.regex' => 'Last name may only contain letters, spaces, hyphens, and apostrophes, no numbers.',
        ];
    }

    /** @param  array<string, mixed>  $validated  already checked with profileRules() */
    public function updateProfile(User $user, array $validated): void
    {
        $user->update($validated);
    }

    /** A profile photo: a real picture (jpeg, png or webp) of at most 1 MB. The website and the phone shrink it to 480 by 480 first. */
    public function photoRules(): array
    {
        return ['photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024']];
    }

    public function photoMessages(): array
    {
        return [
            'photo.required' => 'Choose a photo first.',
            'photo.image' => 'That file is not a picture. Choose a photo (jpg, png or webp).',
            'photo.mimes' => 'Choose a jpg, png or webp photo.',
            'photo.max' => 'That photo is too large. Choose a smaller one, or a photo that is cropped to a square.',
            'photo.uploaded' => 'The photo could not be sent. Try a smaller photo.',
        ];
    }

    /**
     * Keeps the person's own photo. Only a Teacher or a Parent has one; the photo is shown to nobody else (see UserPhotoController).
     *
     * @throws ValidationException under `photo` when the file is not a picture this app keeps
     */
    public function setPhoto(User $user, UploadedFile $file): void
    {
        abort_unless(in_array($user->user_type, ['Teacher', 'Parent'], true), 403);

        try {
            UserPhoto::put($user, $file);
        } catch (\InvalidArgumentException) {
            throw ValidationException::withMessages(['photo' => 'Choose a jpg, png or webp photo.']);
        }
    }

    public function removePhoto(User $user): void
    {
        UserPhoto::remove($user);
    }

    public function passwordRules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated  already checked with passwordRules()
     *
     * @throws ValidationException when the current password is wrong
     */
    public function changePassword(User $user, array $validated): void
    {
        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'That current password is incorrect.']);
        }

        $user->update(['password' => Hash::make($validated['password'])]);
    }

    /** The rules for the "which grades do you handle" form: the grades, and (needed only when adding one) how the school can confirm it. */
    public function gradesRules(): array
    {
        return Teacher::gradeRules() + ['grades_request_note' => ['nullable', 'string', 'max:300']];
    }

    /** The shortest note that can tell the Admin who at the school can confirm a grade (a name and a role). */
    public const GRADE_NOTE_MIN = 8;

    /**
     * Which grades a Teacher handles (one grade, or several, with a separate class for each). Class creation offers only these. Existing
     * classes are never touched by a change here, and a class is still only for its own grade.
     *
     * Who decides depends on the direction. TAKING a grade away, or keeping the same ones, only ever narrows what the teacher can do, so
     * it applies at once. ADDING a grade gives the teacher a new set of classes and learners, which the school (the school head assigns who
     * teaches which grade) has to have agreed to, so an approved teacher's request waits for the Admin (GradeChangeService) and the grades
     * they may open classes for do not change until it is approved. A teacher who is not approved yet is checked as a whole at approval,
     * grades included, so for them it applies at once.
     *
     * @param  array<string, mixed>  $validated  already checked with gradesRules()
     * @return string the message to show
     *
     * @throws ValidationException when a grade is being added and no note says how the school can confirm it
     */
    public function updateGrades(Teacher $teacher, array $validated): string
    {
        $grades = Teacher::gradesFromValidated($validated);
        $current = $teacher->gradesAllowed();
        $added = array_values(array_diff($grades, $current));

        if ($teacher->status === 'Active' && $added !== []) {
            $note = trim((string) ($validated['grades_request_note'] ?? ''));

            if (mb_strlen($note) < self::GRADE_NOTE_MIN) {
                throw ValidationException::withMessages(['grades_request_note' => 'Tell the Admin who at your school can confirm it, for example "Mrs. Cruz, the school head, gave me Grade 2 this year".']);
            }

            $teacher->update(['grades_requested' => $grades, 'grades_requested_at' => now(), 'grades_request_note' => mb_substr($note, 0, 300)]);

            return 'Sent to the Admin: you asked to add '.implode(' and ', $added).'. The Admin checks it with your school before you can open a class for it. Until then you can open classes for '.implode(', ', $current).' only.';
        }

        // Narrowing, or the same grades: nothing new to confirm. Any request that was open is replaced by this choice.
        $teacher->update(['grades_handled' => $grades, 'grades_requested' => null, 'grades_requested_at' => null, 'grades_request_note' => null]);

        $message = $teacher->status === 'Active'
            ? 'Grades updated. New classes will offer: '.implode(', ', $grades).'. Each class still holds one grade only.'
            : 'Grades saved: '.implode(', ', $grades).'. The Admin checks them with your school when approving your account.';

        // Classes of a grade the teacher no longer handles stay as they are, but cannot take new learners.
        $left = SchoolClass::where('teacher_id', $teacher->id)
            ->where('school_year', SchoolClass::currentSchoolYear())
            ->whereNotIn('grade_level', $grades)
            ->get();

        if ($left->isNotEmpty()) {
            $message .= ' You still have '.$left->map(fn ($c) => "{$c->name} ({$c->grade_level})")->implode(', ')
                .'. They stay as they are, but they cannot take new learners until you handle that grade again.';
        }

        return $message;
    }

    /** The teacher takes back their own open request for more grades. Their grades stay exactly as they were. */
    public function cancelGradeRequest(Teacher $teacher): string
    {
        if ($teacher->gradesPending() === []) {
            return 'There was no request waiting.';
        }

        $teacher->update(['grades_requested' => null, 'grades_requested_at' => null, 'grades_request_note' => null]);

        return 'Request cancelled. You still handle '.implode(', ', $teacher->gradesAllowed()).'.';
    }
}

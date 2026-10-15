<?php

namespace App\Services;

use App\Models\SchoolClass;
use App\Models\Teacher;
use App\Models\User;
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

    /**
     * Which grades a Teacher handles (one grade, or several, with a separate class for each). Class creation offers only these. Existing
     * classes are never touched by a change here, and a class is still only for its own grade.
     *
     * @param  array<string, mixed>  $validated  already checked with Teacher::gradeRules()
     * @return string the message to show, with a warning for a class of a grade no longer handled
     */
    public function updateGrades(Teacher $teacher, array $validated): string
    {
        $grades = Teacher::gradesFromValidated($validated);
        $teacher->update(['grades_handled' => $grades]);

        $message = 'Grades updated. New classes will offer: '.implode(', ', $grades).'. Each class still holds one grade only.';

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
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Genuinely missing feature — no actor prompt mentions a Profile
     * screen at all, so this is built from reasonable judgment rather
     * than a quoted spec. Shared by Teacher and Parent (identical
     * underlying User fields); each role gets its own view matching
     * their established color identity, same pattern as
     * Dashboard/Notifications/Analytics already do.
     *
     * Two things deliberately locked, flagged as decisions rather than
     * silently allowed:
     * - Email is not editable here. It's the login identity; changing it
     *   would need its own re-verification flow (a new email is unproven
     *   until confirmed), which is a separate feature, not a form field.
     * - A Teacher's school_name/employee_id are not editable here either.
     *   Both went through real Admin verification (Admin Actor Prompt) —
     *   letting a Teacher silently change verified credentials after
     *   approval would undermine that check. Shown read-only with a note
     *   to contact the Admin instead of a re-verification flow, since no
     *   actor prompt describes one.
     */
    private const NAME_REGEX = '/^[\p{L}\s\'\-]+$/u';

    public function edit(Request $request): View
    {
        $user = $request->user();
        $view = $user->user_type === 'Teacher' ? 'teacher.profile' : 'parent.profile';

        return view($view, [
            'user' => $user,
            'teacher' => $user->user_type === 'Teacher' ? $user->teacher : null,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:255', 'regex:'.self::NAME_REGEX],
            'middle_initial' => ['nullable', 'string', 'max:5'],
            'last_name' => ['required', 'string', 'max:255', 'regex:'.self::NAME_REGEX],
            'contact_number' => ['nullable', 'string', 'max:30'],
        ], [
            'first_name.regex' => 'First name may only contain letters, spaces, hyphens, and apostrophes — no numbers.',
            'last_name.regex' => 'Last name may only contain letters, spaces, hyphens, and apostrophes — no numbers.',
        ]);

        $user->update($validated);

        return back()->with('status', 'Profile updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'That current password is incorrect.',
            ]);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return back()->with('status', 'Password changed.');
    }
}

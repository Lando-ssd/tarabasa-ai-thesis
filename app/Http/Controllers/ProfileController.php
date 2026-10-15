<?php

namespace App\Http\Controllers;

use App\Services\AccountProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly AccountProfileService $profile) {}

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
        $validated = $request->validate($this->profile->profileRules(), $this->profile->profileMessages());

        $this->profile->updateProfile($request->user(), $validated);

        return back()->with('status', 'Profile updated.');
    }

    /**
     * Which grades a Teacher handles (one grade, or several, with a separate class for each). Class creation offers only these. Existing
     * classes are never touched by a change here, and a class is still only for its own grade, so a teacher who handles more grades just
     * opens more classes.
     */
    public function updateGrades(Request $request): RedirectResponse
    {
        abort_unless($request->user()->user_type === 'Teacher', 403);

        $validated = $request->validate(\App\Models\Teacher::gradeRules());

        return back()->with('status', $this->profile->updateGrades($request->user()->teacher, $validated));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $this->profile->changePassword($request->user(), $request->validate($this->profile->passwordRules()));

        return back()->with('status', 'Password changed.');
    }
}

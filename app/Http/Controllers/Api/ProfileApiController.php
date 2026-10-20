<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AccountProfileService;
use App\Services\DataErasureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A Teacher's or Parent's own details in the phone app: their name and contact number, their password, and (a Teacher) the grades they handle.
 * A thin wrapper over AccountProfileService, the same code the website's Profile page calls. The email and a Teacher's school and employee ID
 * are not editable here, as on the website (the Admin verified them).
 */
class ProfileApiController extends Controller
{
    public function __construct(private readonly AccountProfileService $profile) {}

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate($this->profile->profileRules(), $this->profile->profileMessages());
        $this->profile->updateProfile($request->user(), $validated);

        return response()->json(['message' => 'Profile updated.', 'account' => $this->payload($request->user()->refresh())]);
    }

    public function password(Request $request): JsonResponse
    {
        $this->profile->changePassword($request->user(), $request->validate($this->profile->passwordRules()));

        return response()->json(['message' => 'Password changed.']);
    }

    public function grades(Request $request): JsonResponse
    {
        abort_unless($request->user()->user_type === 'Teacher', 403);

        $message = $this->profile->updateGrades($request->user()->teacher, $request->validate($this->profile->gradesRules()));

        return response()->json(['message' => $message, 'account' => $this->payload($request->user()->refresh())]);
    }

    /** A Teacher takes back their own open request for more grades. */
    public function cancelGrades(Request $request): JsonResponse
    {
        abort_unless($request->user()->user_type === 'Teacher', 403);

        $message = $this->profile->cancelGradeRequest($request->user()->teacher);

        return response()->json(['message' => $message, 'account' => $this->payload($request->user()->refresh())]);
    }

    /** What deleting this account would do, in plain sentences, and whether it can be done yet. Shown before the password is asked for. */
    public function eraseInfo(Request $request, DataErasureService $erasure): JsonResponse
    {
        $impact = $erasure->accountImpact($request->user());

        return response()->json(['lines' => $impact['lines'], 'blocked' => $impact['blocked']]);
    }

    /** Deletes the signed in Teacher's or Parent's own account (password again). Every token goes with it, so the app signs out. */
    public function destroy(Request $request, DataErasureService $erasure): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']], ['password.required' => 'Type your password, so we know it is you.']);

        return response()->json(['message' => $erasure->deleteOwnAccount($request->user(), $data['password'])]);
    }

    /** The same account shape sign in gives (AccountApiController), so the app can replace what it holds. */
    private function payload($user): array
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
            $t = $user->teacher;
            $base['teacher'] = AccountApiController::teacherPayload($t);
        }

        return $base;
    }
}

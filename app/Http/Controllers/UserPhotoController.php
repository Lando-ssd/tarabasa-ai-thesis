<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Hands out a Teacher's or Parent's own profile photo, which is kept in the database (see UserPhoto).
 *
 * There is no public address for it. Only the person it belongs to sees it, signed in on the website or with their own phone token.
 * Everyone else, signed in or not, a child, a parent of the teacher's learners and the Administrator included, gets "not found"
 * (never "forbidden"), so the address cannot be used to find out whether someone exists or has a photo. Nothing in the other screens
 * needs to show an adult's photo to anyone else, so nothing does.
 *
 * Like a child's photo, the address carries `?v=` (the photo's version), so a browser may keep a picture for an hour and a replaced
 * photo is fetched at once. SecurityHeaders leaves this route's cache header alone for that reason.
 */
class UserPhotoController extends Controller
{
    public function show(Request $request, User $user): Response
    {
        abort_unless($user->photo_version && $this->isOwner($user), 404);

        $etag = '"user-photo-'.$user->id.'-'.$user->photo_version.'"';
        $headers = ['ETag' => $etag, 'Cache-Control' => 'private, max-age=3600'];

        if (in_array($etag, array_map('trim', explode(',', (string) $request->header('If-None-Match'))), true)) {
            return response('', 304, $headers);
        }

        $photo = UserPhoto::where('user_id', $user->id)->first();
        $bytes = $photo ? base64_decode($photo->data, true) : false;

        abort_if($bytes === false || ! in_array($photo->mime, UserPhoto::TYPES, true), 404);

        return response($bytes, 200, $headers + [
            'Content-Type' => $photo->mime,
            'Content-Disposition' => 'inline',
            'Content-Length' => (string) strlen($bytes),
        ]);
    }

    /**
     * The phone's own address for its own photo (GET /api/auth/profile/photo): no id to guess, and it sits under the app's sign in checks
     * (a child's or an Administrator's token never reaches it). It also sits under /api, which is where cross origin access is allowed,
     * so the browser version of the app can show the photo too.
     */
    public function mine(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 404);

        return $this->show($request, $user);
    }

    private function isOwner(User $user): bool
    {
        foreach ([Auth::guard('web')->user(), Auth::guard('sanctum')->user()] as $who) {
            // A deactivated account may still hold a phone token until its next call; it sees nothing meanwhile.
            if ($who instanceof User && $who->id === $user->id && ($who->status ?? 'Active') === 'Active') {
                return true;
            }
        }

        return false;
    }
}

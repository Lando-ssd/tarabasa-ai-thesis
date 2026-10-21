<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\LearnerPhoto;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Hands out a child's photo, which is kept in the database (see LearnerPhoto).
 *
 * There is no public address for a photo. It is shown only to:
 *  - the child, signed in on their own device (or with their own phone token);
 *  - a parent or guardian linked to the child;
 *  - the teacher of the class the child is in.
 * Anyone else, signed in or not, gets "not found" (never "forbidden"), so the address cannot be used to find out whether a child
 * exists or has a photo. An Admin does not see children's photos either: nothing in the Admin screens needs them.
 *
 * The address carries `?v=` (the photo's version), so a browser may keep a picture for an hour and a replaced photo is fetched at
 * once. SecurityHeaders leaves this route's cache header alone for that reason (see the route's name there).
 */
class LearnerPhotoController extends Controller
{
    public function show(Request $request, Learner $learner): Response
    {
        abort_unless($learner->photo_version && $this->mayView($learner), 404);

        $etag = '"photo-'.$learner->id.'-'.$learner->photo_version.'"';
        $headers = ['ETag' => $etag, 'Cache-Control' => 'private, max-age=3600'];

        // The browser already has this very picture: say so without sending it again.
        if (in_array($etag, array_map('trim', explode(',', (string) $request->header('If-None-Match'))), true)) {
            return response('', 304, $headers);
        }

        $photo = LearnerPhoto::where('learner_id', $learner->id)->first();
        $bytes = $photo ? base64_decode($photo->data, true) : false;

        abort_if($bytes === false || ! in_array($photo->mime, LearnerPhoto::TYPES, true), 404);

        return response($bytes, 200, $headers + [
            'Content-Type' => $photo->mime,
            'Content-Disposition' => 'inline',
            'Content-Length' => (string) strlen($bytes),
        ]);
    }

    private function mayView(Learner $learner): bool
    {
        // The child on their own device.
        $child = Auth::guard('learner')->user();
        if ($child instanceof Learner && $child->id === $learner->id) {
            return true;
        }

        // An adult signed in on the website, or a phone's token (a child's own, or an adult's).
        foreach ([Auth::guard('web')->user(), Auth::guard('sanctum')->user()] as $who) {
            if ($who instanceof Learner && $who->id === $learner->id) {
                return true;
            }

            if ($who instanceof User && $this->adultMayView($who, $learner)) {
                return true;
            }
        }

        return false;
    }

    private function adultMayView(User $user, Learner $learner): bool
    {
        // A deactivated account may still hold a phone token until its next call; it does not see photos meanwhile.
        if (($user->status ?? 'Active') !== 'Active') {
            return false;
        }

        if ($user->user_type === 'Parent') {
            return (bool) $user->parentProfile?->learners()->whereKey($learner->id)->exists();
        }

        if ($user->user_type === 'Teacher' && $learner->class_id) {
            $teacherId = $user->teacher?->id;

            return $teacherId !== null && SchoolClass::whereKey($learner->class_id)->where('teacher_id', $teacherId)->exists();
        }

        return false;
    }
}

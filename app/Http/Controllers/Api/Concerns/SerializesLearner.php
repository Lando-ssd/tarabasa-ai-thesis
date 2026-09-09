<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Learner;
use Illuminate\Support\Facades\Storage;

/**
 * Shared between LearnerApiController and LearnerReadingApiController —
 * both need to hand a Learner back to the client after an action changes
 * it (login, dashboard, a scored reading). Deliberately whitelisted
 * fields, never the raw Eloquent model (that would expose the hashed pin,
 * internal timestamps, etc.).
 */
trait SerializesLearner
{
    private function learnerPayload(Learner $learner): array
    {
        return [
            'id' => $learner->id,
            'learnerCode' => $learner->learner_code,
            'firstName' => $learner->first_name,
            'gradeLevel' => $learner->grade_level,
            'avatarId' => $learner->avatar_id,
            'avatarPhotoUrl' => $learner->avatar_photo_path ? Storage::url($learner->avatar_photo_path) : null,
            'masteryLevel' => $learner->mastery_level ?? 'New',
            'points' => $learner->points,
            'streak' => $learner->streak,
            'readingFontStep' => $learner->effectiveReadingFontStep(),
        ];
    }
}

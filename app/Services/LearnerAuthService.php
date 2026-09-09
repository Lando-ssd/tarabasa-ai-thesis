<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\OpenRepositoryListing;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The real credential-check/throttle logic behind Learner login, and the
 * real "what should I read?" picker resolution (Teacher-assigned +
 * repository-unlocked + Adaptive_Recommendator's pick) — extracted out of
 * LearnerAuthController so the exact same logic backs both the web
 * session-cookie login and the mobile Sanctum-token login, and both the
 * web Blade picker and the mobile JSON picker. See CLAUDE.md's "Mobile
 * API layer" entry.
 */
class LearnerAuthService
{
    /**
     * No password, no email — just learnerCode + a 4-digit PIN. A 4-digit
     * PIN has only 10,000 combinations, so this is throttled far more
     * aggressively than the adult login: 5 wrong attempts locks out for
     * 15 minutes. Keyed by the raw submitted code (not a resolved Learner
     * ID) so an unknown code and a real code with a wrong PIN are
     * throttled identically — neither the error nor the lockout behavior
     * may reveal whether the code itself exists. Throws the same
     * ValidationException shape either channel already renders correctly
     * (Blade re-shows the form with the error; Laravel's JSON exception
     * rendering — already forced for api/* requests — turns it into a
     * real 422 with the same message).
     */
    public function authenticate(string $rawCode, string $pin, string $ip): Learner
    {
        $code = strtoupper(trim($rawCode));
        $throttleKey = 'learner-login:'.$code.'|'.$ip;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);

            throw ValidationException::withMessages([
                'pin' => "Too many attempts. Try again in {$minutes} minute".($minutes === 1 ? '' : 's').'.',
            ]);
        }

        $learner = Learner::where('learner_code', $code)->first();

        if (! $learner || ! Hash::check($pin, $learner->pin)) {
            RateLimiter::hit($throttleKey, 900);

            throw ValidationException::withMessages([
                'pin' => 'Incorrect PIN.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        return $learner;
    }

    /**
     * Delegates to LearnerDiagnosticService, which owns both the
     * diagnostic's in-progress cache state AND its ReadingSession records
     * — the only place that can correctly tell "genuinely finished" apart
     * from "started but not done yet." See that method's own doc comment
     * for a real bug this fixed.
     */
    public function hasCompletedDiagnostic(Learner $learner): bool
    {
        return app(LearnerDiagnosticService::class)->hasGenuinelyCompletedDiagnostic($learner);
    }

    /**
     * Learner Actor Prompt Step 3: resolve the Teacher-assigned Activity
     * using priority — direct-to-this-Learner first, then Class, then
     * Class's Group tag — plus any Open-Repository-unlocked Activities,
     * always included alongside the assignment result. Returns a plain
     * Collection of ['activity' => Activity, 'source' => string] — the
     * same shape both the Blade picker and the JSON picker render from.
     */
    public function findActivityOptions(Learner $learner): Collection
    {
        $assignmentIds = ActivityAssignment::where('learner_id', $learner->id)->pluck('activity_id');

        if ($assignmentIds->isEmpty() && $learner->class_id) {
            $assignmentIds = ActivityAssignment::where('class_id', $learner->class_id)->pluck('activity_id');
        }

        if ($assignmentIds->isEmpty() && $learner->schoolClass?->group_tag) {
            $assignmentIds = ActivityAssignment::where('group_tag', $learner->schoolClass->group_tag)
                ->where('assigned_by_teacher_id', $learner->schoolClass->teacher_id)
                ->pluck('activity_id');
        }

        $assignedActivities = Activity::whereIn('id', $assignmentIds)
            ->where('status', 'Approved')
            ->get();

        $unlockedListingActivityIds = OpenRepositoryListing::whereHas(
            'unlocks',
            fn ($query) => $query->where('learner_id', $learner->id)
        )->pluck('activity_id');

        $unlockedActivities = Activity::whereIn('id', $unlockedListingActivityIds)
            ->where('status', 'Approved')
            ->whereNotIn('id', $assignedActivities->pluck('id'))
            ->get();

        $options = $assignedActivities->map(fn ($activity) => ['activity' => $activity, 'source' => 'Assigned by your Teacher'])
            ->concat($unlockedActivities->map(fn ($activity) => ['activity' => $activity, 'source' => 'Extra Practice']))
            ->values();

        return $this->applyAdaptiveRecommendation($learner, $options);
    }

    /**
     * Adaptive_Recommendator step (Learner Actor Prompt Step 3's "Adaptive
     * Recommend" stage) — labels and prioritizes one already-available
     * option to the front of the list, rather than ever generating new
     * content on the Learner's behalf. If nothing available matches the
     * recommendation, the options are returned completely unchanged —
     * never a misleading label on a near-match.
     */
    private function applyAdaptiveRecommendation(Learner $learner, Collection $options): Collection
    {
        if (! $learner->next_recommended_competency) {
            return $options;
        }

        $matchIndex = $options->search(function (array $option) use ($learner) {
            $activity = $option['activity'];

            if ($activity->competency !== $learner->next_recommended_competency) {
                return false;
            }

            return $learner->next_recommended_difficulty === null
                || strtolower($activity->difficulty_tier) === $learner->next_recommended_difficulty;
        });

        if ($matchIndex === false) {
            return $options;
        }

        $recommended = $options->get($matchIndex);
        $recommended['source'] = 'Picked just for you! 🎯';

        return collect([$recommended])
            ->concat($options->reject(fn ($option, int $i) => $i === $matchIndex)->values());
    }
}

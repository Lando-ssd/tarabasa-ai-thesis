<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityAssignment;
use App\Models\Learner;
use App\Models\OpenRepositoryListing;
use App\Support\ErrorPatterns;
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
        $code = \App\Support\LearnerCode::normalize($rawCode);
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
                'pin' => 'Incorrect code or PIN.',
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
     * Learner Actor Prompt Step 3: gather what a Teacher gave this Learner (directly, to
     * their class or reading group, or to a focus group their class carries) plus any
     * Open-Repository-unlocked Activities. All sources are shown together: the same rule the
     * access check uses (ActivityAssignment::reachingLearner), so a child is never shown less
     * than they may open. (This used to stop at the first source that had anything, which hid a
     * class activity from a child who also had a direct one.) Returns a plain
     * Collection of ['activity' => Activity, 'source' => string] — the
     * same shape both the Blade picker and the JSON picker render from.
     */
    public function findActivityOptions(Learner $learner): Collection
    {
        $assignmentIds = ActivityAssignment::reachingLearner($learner)->pluck('activity_id')->unique();

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

        return $this->applyPatternPractice($learner, $this->applyAdaptiveRecommendation($learner, $options));
    }

    /**
     * How a repeating kind of mistake is said to a CHILD: what to practise, never what went wrong.
     * (The adult wording lives in ErrorPatterns::CATEGORIES.)
     */
    private const PRACTICE_WORDING = [
        'small_words' => 'Practice small words',
        'endings' => 'Practice word endings',
        'vowels' => 'Practice middle sounds',
        'beginning' => 'Practice first sounds',
        'ending_sound' => 'Practice last sounds',
        'blends' => 'Practice letter blends',
        'look_alike' => 'Practice looking closely at letters',
        'skipped' => 'Practice reading every word',
        'other' => 'Practice sounding words out',
    ];

    /**
     * Uses what the child's own readings show (ErrorPatterns). When there is a clear repeating kind of
     * mistake, the available story that practises it best is moved up (just behind the recommender's
     * own pick) and tagged with what it practises. It only ever reorders what the child may already
     * open, so a teacher's assignment is never hidden, and it says nothing when there is no clear
     * pattern or no story fits well (the same 0.15 bar the Teacher's suggestions use).
     */
    private function applyPatternPractice(Learner $learner, Collection $options): Collection
    {
        if ($options->count() < 2) {
            return $options;
        }

        $profile = ErrorPatterns::forLearner($learner);
        $pattern = ErrorPatterns::mainPattern($profile);

        if ($pattern === null) {
            return $options;
        }

        $best = null;
        $bestFit = 0.15;

        foreach ($options as $i => $option) {
            // The recommender's own pick keeps its own label and place.
            if (str_contains($option['source'], 'Picked just for you')) {
                continue;
            }

            $fit = ErrorPatterns::fit($option['activity'], $pattern, $profile['missedWords']);

            if ($fit > $bestFit) {
                $best = $i;
                $bestFit = $fit;
            }
        }

        if ($best === null) {
            return $options;
        }

        $chosen = $options->get($best);
        $chosen['practice'] = self::PRACTICE_WORDING[$pattern] ?? self::PRACTICE_WORDING['other'];

        $rest = $options->reject(fn ($option, int $i) => $i === $best)->values();
        $position = $rest->isNotEmpty() && str_contains($rest->first()['source'], 'Picked just for you') ? 1 : 0;

        return $rest->slice(0, $position)->concat([$chosen])->concat($rest->slice($position))->values();
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
        $focus = $learner->focusSubdomain();

        if (! $focus) {
            return $options;
        }

        $focusDifficulty = $learner->focusDifficulty();
        $alignment = app(MatatagAlignmentResolver::class);

        $matchIndex = $options->search(function (array $option) use ($alignment, $focus, $focusDifficulty) {
            $activity = $option['activity'];

            if ($alignment->subdomainFor($activity) !== $focus) {
                return false;
            }

            return $focusDifficulty === null
                || strtolower($activity->difficulty_tier) === $focusDifficulty;
        });

        if ($matchIndex === false) {
            return $options;
        }

        $recommended = $options->get($matchIndex);
        $recommended['source'] = 'Picked just for you';

        return collect([$recommended])
            ->concat($options->reject(fn ($option, int $i) => $i === $matchIndex)->values());
    }
}

<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\OpenRepositoryListing;
use App\Models\ParentAccount;
use App\Models\RepositoryRating;
use App\Models\RepositoryUnlock;
use App\Support\ErrorPatterns;
use App\Support\ReadingLevel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Browse and unlock the Open Repository, and rate what was unlocked (Parent Actor Prompt Step 6, RepositoryRatings_Addition.txt).
 * The website's RepositoryController and the phone app's Api\ParentApiController both call this, so what suits a child, what an unlock
 * records and who may rate cannot drift apart.
 */
class ParentRepositoryService
{
    /**
     * The listings, best match for this child first. Platform wide (every teacher's shared content, not limited to the child's own
     * teacher), with unlock status for this one child, since unlocking is always per item and per child. What suits the child: the
     * activity level for their reading level (Phil-IRI, see ReadingLevel) and the kind of mistake they keep making (ErrorPatterns),
     * both said plainly on the card.
     *
     * @param  'all'|'free'|'paid'|'unlocked'  $filter
     * @return array{rows: Collection<int, array<string, mixed>>, hasMatching: bool}
     */
    public function rows(ParentAccount $parent, ?Learner $learner, string $filter = 'all', bool $onlyBest = false): array
    {
        $wantedTier = null;
        $pattern = null;
        $missed = [];

        if ($learner) {
            $band = ReadingLevel::forAdult($learner)['band'];
            $wantedTier = $band === 'unchecked' ? null : ReadingLevel::tierForBand($band);
            $profile = ErrorPatterns::forLearner($learner);
            $pattern = ErrorPatterns::mainPattern($profile);
            $missed = $profile['missedWords'];
        }

        $rows = OpenRepositoryListing::with(['activity', 'teacher.user', 'ratings'])
            ->whereHas('activity', fn ($query) => $query->where('status', 'Approved'))
            ->get()
            ->map(function (OpenRepositoryListing $listing) use ($learner, $parent, $wantedTier, $pattern, $missed) {
                $isUnlocked = $learner && $listing->isUnlockedFor($learner->id);
                $myRating = $learner
                    ? RepositoryRating::where('listing_id', $listing->id)->where('parent_id', $parent->id)->first()
                    : null;

                $activity = $listing->activity;
                $tierMatch = $wantedTier !== null && $activity->difficulty_tier === $wantedTier;
                $levelMatch = $tierMatch && $learner && $activity->grade_level === $learner->grade_level;
                $patternMatch = $pattern !== null && ErrorPatterns::fit($activity, $pattern, $missed) > 0.15;
                $avg = $listing->ratings->isNotEmpty() ? round($listing->ratings->avg('rating'), 1) : null;

                return [
                    'listing' => $listing,
                    'is_unlocked' => $isUnlocked,
                    'avg_rating' => $avg,
                    'rating_count' => $listing->ratings->count(),
                    'my_rating' => $myRating,
                    // Why this suits the child, said as short tags.
                    'level_match' => $levelMatch,
                    'pattern_match' => $patternMatch ? strtolower(ErrorPatterns::CATEGORIES[$pattern]['label']) : null,
                    'score' => ($levelMatch ? 4 : 0) + ($tierMatch ? 2 : 0) + ($patternMatch ? 3 : 0) + (($avg ?? 0) / 5),
                ];
            })
            ->sort(fn ($a, $b) => [$b['score'], $b['listing']->id] <=> [$a['score'], $a['listing']->id])
            ->when($onlyBest, fn ($rows) => $rows->filter(fn ($row) => $row['level_match'] || $row['pattern_match'] !== null))
            ->when($filter === 'free', fn ($rows) => $rows->filter(fn ($row) => $row['listing']->price_type === 'Free'))
            ->when($filter === 'paid', fn ($rows) => $rows->filter(fn ($row) => $row['listing']->price_type === 'Paid'))
            ->when($filter === 'unlocked', fn ($rows) => $rows->filter(fn ($row) => $row['is_unlocked']))
            ->values();

        return ['rows' => $rows, 'hasMatching' => $wantedTier !== null || $pattern !== null];
    }

    /**
     * Free unlocks at once; Paid is confirmed in the app's own second step (a real payment gateway is out of scope: Step 6 calls it
     * "simulated payment" verbatim). Either way this records the real unlock. Pressed twice at the same moment, the database refuses
     * the second, and so does this.
     *
     * @throws ValidationException with the message under `unlock`
     */
    public function unlock(ParentAccount $parent, OpenRepositoryListing $listing, Learner $learner): void
    {
        if ($listing->isUnlockedFor($learner->id)) {
            throw ValidationException::withMessages(['unlock' => 'Already unlocked for '.$learner->first_name.'.']);
        }

        try {
            RepositoryUnlock::create([
                'listing_id' => $listing->id,
                'parent_id' => $parent->id,
                'learner_id' => $learner->id,
                'amount_paid' => $listing->price_type === 'Paid' ? $listing->price : 0,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['unlock' => 'Already unlocked for '.$learner->first_name.'.']);
        }
    }

    /**
     * Only a Parent with a real unlock for this listing may rate it (checked here, not just hidden in the screen), one rating per
     * Parent per listing: rating again updates the existing row, matching the schema's UNIQUE(listing_id, parent_id) as given.
     */
    public function rate(ParentAccount $parent, OpenRepositoryListing $listing, Learner $learner, int $rating, ?string $comment): void
    {
        $hasUnlock = RepositoryUnlock::where('listing_id', $listing->id)
            ->whereIn('learner_id', $parent->learners()->pluck('learners.id'))
            ->exists();
        abort_unless($hasUnlock, 403, 'You can only rate content you\'ve unlocked.');

        RepositoryRating::updateOrCreate(
            ['listing_id' => $listing->id, 'parent_id' => $parent->id],
            ['learner_id' => $learner->id, 'rating' => $rating, 'comment' => $comment]
        );
    }
}

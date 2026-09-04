<?php

namespace App\Http\Controllers;

use App\Models\Learner;
use App\Models\OpenRepositoryListing;
use App\Models\RepositoryRating;
use App\Models\RepositoryUnlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RepositoryController extends Controller
{
    /**
     * Browse & Unlock the Open Repository — Parent Actor Prompt Step 6.
     * Platform-wide listing grid (every Teacher's shared content, NOT
     * limited to the Parent's own child's Teacher), scoped per-Learner
     * for unlock status since unlocking is always per-item, per-specific-
     * Learner. Ratings (RepositoryRatings_Addition.txt) shown as an
     * average + count on every card.
     */
    public function index(Request $request): View
    {
        $parent = $request->user()->parentProfile;
        $learners = $parent->learners()->orderBy('first_name')->get();

        $selectedLearner = null;
        if ($learners->isNotEmpty()) {
            $requestedId = (int) $request->query('learner_id', $learners->first()->id);
            $selectedLearner = $learners->firstWhere('id', $requestedId) ?? $learners->first();
        }

        $filter = in_array($request->query('filter'), ['free', 'paid', 'unlocked'], true)
            ? $request->query('filter')
            : 'all';

        $listings = OpenRepositoryListing::with(['activity', 'teacher.user', 'ratings'])
            ->whereHas('activity', fn ($query) => $query->where('status', 'Approved'))
            ->get()
            ->map(function (OpenRepositoryListing $listing) use ($selectedLearner, $parent) {
                $isUnlocked = $selectedLearner && $listing->isUnlockedFor($selectedLearner->id);
                $myRating = $selectedLearner
                    ? RepositoryRating::where('listing_id', $listing->id)->where('parent_id', $parent->id)->first()
                    : null;

                return [
                    'listing' => $listing,
                    'is_unlocked' => $isUnlocked,
                    'avg_rating' => $listing->ratings->isNotEmpty() ? round($listing->ratings->avg('rating'), 1) : null,
                    'rating_count' => $listing->ratings->count(),
                    'my_rating' => $myRating,
                ];
            })
            ->when($filter === 'free', fn ($rows) => $rows->filter(fn ($row) => $row['listing']->price_type === 'Free'))
            ->when($filter === 'paid', fn ($rows) => $rows->filter(fn ($row) => $row['listing']->price_type === 'Paid'))
            ->when($filter === 'unlocked', fn ($rows) => $rows->filter(fn ($row) => $row['is_unlocked']))
            ->values();

        return view('parent.repository', [
            'user' => $request->user(),
            'learners' => $learners,
            'selectedLearner' => $selectedLearner,
            'filter' => $filter,
            'rows' => $listings,
        ]);
    }

    /**
     * Free unlocks instantly; Paid is "confirmed" by the two-step reveal
     * in the UI (a real payment gateway is explicitly out of scope —
     * Parent Actor Prompt Step 6 calls this "(simulated payment)"
     * verbatim) — either way this one endpoint records the real unlock.
     */
    public function unlock(Request $request, OpenRepositoryListing $listing): RedirectResponse
    {
        $parent = $request->user()->parentProfile;

        $validated = $request->validate([
            'learner_id' => ['required', 'integer'],
        ]);

        $learner = $parent->learners()->find($validated['learner_id']);
        abort_unless($learner, 403, 'That child is not linked to your account.');

        if ($listing->isUnlockedFor($learner->id)) {
            throw ValidationException::withMessages([
                'unlock' => 'Already unlocked for '.$learner->first_name.'.',
            ]);
        }

        RepositoryUnlock::create([
            'listing_id' => $listing->id,
            'parent_id' => $parent->id,
            'learner_id' => $learner->id,
            'amount_paid' => $listing->price_type === 'Paid' ? $listing->price : 0,
        ]);

        return redirect()
            ->route('parent.repository.index', ['learner_id' => $learner->id])
            ->with('status', "Unlocked \"{$listing->activity->title}\" for {$learner->first_name}.");
    }

    /**
     * RepositoryRatings_Addition.txt: only a Parent with a real unlock
     * for this listing may rate it (server-enforced, not just hidden in
     * the UI), one rating per Parent per listing — re-rating UPDATEs the
     * existing row rather than creating a duplicate, exactly matching
     * the schema's UNIQUE(listing_id, parent_id) constraint as given
     * (confirmed with the user: no per-Learner deviation).
     */
    public function rate(Request $request, OpenRepositoryListing $listing): RedirectResponse
    {
        $parent = $request->user()->parentProfile;

        $validated = $request->validate([
            'learner_id' => ['required', 'integer'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $learner = $parent->learners()->find($validated['learner_id']);
        abort_unless($learner, 403, 'That child is not linked to your account.');

        $hasUnlock = RepositoryUnlock::where('listing_id', $listing->id)
            ->whereIn('learner_id', $parent->learners()->pluck('learners.id'))
            ->exists();
        abort_unless($hasUnlock, 403, 'You can only rate content you\'ve unlocked.');

        RepositoryRating::updateOrCreate(
            ['listing_id' => $listing->id, 'parent_id' => $parent->id],
            ['learner_id' => $learner->id, 'rating' => $validated['rating'], 'comment' => $validated['comment'] ?? null]
        );

        return redirect()
            ->route('parent.repository.index', ['learner_id' => $learner->id])
            ->with('status', 'Thanks for rating "'.$listing->activity->title.'"!');
    }
}

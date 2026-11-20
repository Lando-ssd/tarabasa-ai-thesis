<?php

namespace App\Http\Controllers;

use App\Models\OpenRepositoryListing;
use App\Models\Payment;
use App\Services\ParentRepositoryService;
use App\Services\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RepositoryController extends Controller
{
    public function __construct(private readonly ParentRepositoryService $repository) {}

    /**
     * Browse & Unlock the Open Repository — Parent Actor Prompt Step 6.
     * Platform-wide listing grid (every Teacher's shared content, NOT
     * limited to the Parent's own child's Teacher), scoped per-Learner
     * for unlock status since unlocking is always per-item, per-specific-
     * Learner. Ratings (RepositoryRatings_Addition.txt) shown as an
     * average + count on every card. The rules live in ParentRepositoryService.
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

        // Only matches for this child (their level, their pattern), when asked for.
        $onlyBest = $request->query('best') === '1';

        $found = $this->repository->rows($parent, $selectedLearner, $filter, $onlyBest);

        // Test payments (config/payments.php): off by default, and then the page is exactly what it always was. When on, a paid activity goes
        // to the provider's test page. A checkout that is still open for this child is offered again instead of starting a second one.
        $pendingListingIds = $selectedLearner && PaymentService::enabled()
            ? Payment::where('parent_id', $parent->id)->where('learner_id', $selectedLearner->id)->where('status', Payment::PENDING)
                ->where('expires_at', '>', now())->whereNotNull('checkout_url')->pluck('listing_id')->all()
            : [];

        return view('parent.repository', [
            'user' => $request->user(),
            'learners' => $learners,
            'selectedLearner' => $selectedLearner,
            'filter' => $filter,
            'onlyBest' => $onlyBest,
            'hasMatching' => $found['hasMatching'],
            'rows' => $found['rows'],
            'paymentsOn' => PaymentService::enabled(),
            'paymentsReady' => PaymentService::ready(),
            'paymentsDemo' => PaymentService::isDemo(),
            'paymentWord' => PaymentService::word(),
            'pendingListingIds' => $pendingListingIds,
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

        $this->repository->unlock($parent, $listing, $learner);

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

        $this->repository->rate($parent, $listing, $learner, (int) $validated['rating'], $validated['comment'] ?? null);

        return redirect()
            ->route('parent.repository.index', ['learner_id' => $learner->id])
            ->with('status', 'Thanks for rating "'.$listing->activity->title.'"!');
    }
}

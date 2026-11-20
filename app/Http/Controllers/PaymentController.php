<?php

namespace App\Http\Controllers;

use App\Models\OpenRepositoryListing;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The Parent's side of a test payment (config/payments.php, App\Services\PaymentService). The screens are the ones in the approved preview:
 * "Go to test payment" (start), the page the parent comes back to (back and cancelled), the quiet check it does while it waits (status) and the
 * list of attempts (index). Every page belongs to ONE parent: someone else's reference is "not found".
 *
 * The page the parent comes back to only SHOWS the result. It asks the provider (PaymentService::refresh) and never unlocks anything by itself.
 */
class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /** "Go to test payment": make the record, then send the parent to the provider's own page. */
    public function start(Request $request, OpenRepositoryListing $listing): RedirectResponse
    {
        $parent = $request->user()->parentProfile;
        $validated = $request->validate(['learner_id' => ['required', 'integer']]);

        $learner = $parent->learners()->find($validated['learner_id']);
        abort_unless($learner, 403, 'That child is not linked to your account.');

        try {
            $payment = $this->payments->start($parent, $listing, $learner);
        } catch (ValidationException $e) {
            return redirect()->route('parent.repository.index', ['learner_id' => $learner->id])->withErrors($e->errors());
        }

        return redirect()->away((string) $payment->checkout_url);
    }

    /** The provider sent the parent back after paying. */
    public function back(Request $request, string $reference): View
    {
        $payment = $this->payments->refresh($this->owned($request, $reference));

        return $this->result($payment);
    }

    /** The provider sent the parent back through "cancel". */
    public function cancelled(Request $request, string $reference): View
    {
        $payment = $this->payments->cancel($this->owned($request, $reference));

        return $this->result($payment);
    }

    /** What the waiting page asks every few seconds. */
    public function status(Request $request, string $reference): JsonResponse
    {
        $payment = $this->payments->refresh($this->owned($request, $reference));

        return response()->json(['status' => $payment->status]);
    }

    /** Every attempt, newest first. */
    public function index(Request $request): View
    {
        $parent = $request->user()->parentProfile;

        return view('parent.payments.index', [
            'payments' => $this->payments->history($parent),
            'paymentsOn' => PaymentService::enabled(),
        ]);
    }

    private function owned(Request $request, string $reference): Payment
    {
        $parent = $request->user()->parentProfile;

        return Payment::with(['listing.activity', 'learner'])->where('reference', $reference)->where('parent_id', $parent->id)->firstOrFail();
    }

    private function result(Payment $payment): View
    {
        $payment->loadMissing(['listing.activity', 'learner']);

        return view('parent.payments.result', ['payment' => $payment]);
    }
}

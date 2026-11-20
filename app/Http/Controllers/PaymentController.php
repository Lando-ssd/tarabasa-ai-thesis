<?php

namespace App\Http\Controllers;

use App\Models\OpenRepositoryListing;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    /**
     * The provider sent the parent back through "cancel". This is a GET (the provider redirects the browser), so a browser that only PRE-loads
     * the address (a prefetch) must not cancel anything: it is shown the current state instead.
     */
    public function cancelled(Request $request, string $reference): View
    {
        $payment = $this->owned($request, $reference);
        $prefetch = str_contains(strtolower($request->header('Sec-Purpose', '').' '.$request->header('Purpose', '')), 'prefetch');
        $payment = $prefetch ? $this->payments->refresh($payment) : $this->payments->cancel($payment);

        return $this->result($payment);
    }

    // ------------------------------------------------------------------------------------------------------------- the demo checkout

    /**
     * TaraBasa's own demo checkout (PAYMENTS_MODE=demo): choose how to pay and what should happen in the demo. The payment must be this parent's,
     * made by the demo, and still pending; otherwise the parent is sent to the result page, which shows what became of it.
     */
    public function demo(Request $request, string $reference): View|RedirectResponse
    {
        $payment = $this->ownedDemo($request, $reference);
        if (! $payment->isPending()) {
            return redirect()->route('parent.payments.back', $payment->reference);
        }

        return view('parent.payments.demo', [
            'payment' => $payment,
            'methods' => (array) config('payments.demo.methods'),
            'outcomes' => self::OUTCOMES,
            'method' => $request->old('method'),
            'outcome' => $request->old('outcome', 'approve'),
        ]);
    }

    /** The pretend step for the chosen way to pay: a read only card, or one approve button for a wallet. Nothing can be typed. */
    public function demoStep(Request $request, string $reference): View|RedirectResponse
    {
        $payment = $this->ownedDemo($request, $reference);
        if (! $payment->isPending()) {
            return redirect()->route('parent.payments.back', $payment->reference);
        }

        $data = $request->validate($this->demoRules(), ['method.required' => 'Choose how to pay.', 'method.in' => 'Choose how to pay.']);

        return view('parent.payments.demo-step', [
            'payment' => $payment,
            'methodKey' => $data['method'],
            'methodName' => config('payments.demo.methods')[$data['method']],
            'outcome' => $data['outcome'],
            'outcomeName' => self::OUTCOMES[$data['outcome']],
        ]);
    }

    /** "Pay (pretend)": the demo answer, then the result page like any other payment. */
    public function demoPay(Request $request, string $reference): RedirectResponse
    {
        $payment = $this->ownedDemo($request, $reference);
        $data = $request->validate($this->demoRules(), ['method.required' => 'Choose how to pay.', 'method.in' => 'Choose how to pay.']);

        $this->payments->demoPay($payment, $data['method'], $data['outcome']);

        return redirect()->route('parent.payments.back', $payment->reference);
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

    /** The endings a parent can choose on the demo checkout page, and the words for them. */
    private const OUTCOMES = ['approve' => 'Approved', 'decline' => 'Declined', 'cancel' => 'Cancelled'];

    /** @return array<string, mixed> */
    private function demoRules(): array
    {
        return [
            'method' => ['required', Rule::in(array_keys((array) config('payments.demo.methods')))],
            'outcome' => ['required', Rule::in(array_keys(self::OUTCOMES))],
        ];
    }

    /** This parent's payment, and only when it was made by the demo checkout and the demo is the mode that runs now (otherwise not found). */
    private function ownedDemo(Request $request, string $reference): Payment
    {
        $payment = $this->owned($request, $reference);
        abort_unless(PaymentService::isDemo() && $payment->isDemo(), 404);

        return $this->payments->expireIfDue($payment)->loadMissing(['listing.activity', 'learner']);
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

<?php

namespace App\Services;

use App\Models\Learner;
use App\Models\OpenRepositoryListing;
use App\Models\ParentAccount;
use App\Models\Payment;
use App\Support\PaymongoSignature;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Test mode payments for a paid activity (config/payments.php). The website's PaymentController and the provider's message
 * (Api\PaymongoWebhookController) both go through here, so one set of rules decides when an unlock is allowed.
 *
 * The order of events (the approved preview, "How a transaction works"):
 *   1. start()        a record is made (status pending) and the provider is asked for a checkout address with only the price, the activity title
 *                     and a reference number. The parent is then sent to the provider's page, where card, GCash or Maya is chosen. TaraBasa never
 *                     sees a card or a wallet number.
 *   2. the provider   tells our server the result with a SIGNED message (handleWebhook), or the parent comes back and we ask the provider
 *                     (refresh). The page the parent lands on never decides anything.
 *   3. applyPaid()    only a "paid" result whose reference, session and AMOUNT match saves the unlock, once. A repeated message changes nothing.
 *
 * What the website can see is paid, cancelled, expired and (could not start) failed. A failed attempt on the provider's own page is handled there:
 * the parent chooses another way on that page, so there is no "failed" status to receive.
 *
 * TEST MODE ONLY: ready() refuses a key that does not start with sk_test_, and a message that says it came from live mode is ignored.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymongoClient $client,
        private readonly ParentRepositoryService $repository,
    ) {}

    /** The switch (PAYMENTS_ENABLED). Off by default: a paid unlock is then the old simulated step and nothing leaves the site. */
    public static function enabled(): bool
    {
        return (bool) config('payments.enabled');
    }

    /** On, and a test key is in place. A live key is never accepted. */
    public static function ready(): bool
    {
        return self::enabled() && str_starts_with((string) config('payments.paymongo.secret_key'), 'sk_test_');
    }

    // ------------------------------------------------------------------------------------------------------------- start

    /**
     * Make the record and ask for the checkout address. The same open checkout is used again when the same parent presses twice, or comes back to
     * the list while one is still open.
     *
     * @throws ValidationException with the message under `unlock`
     */
    public function start(ParentAccount $parent, OpenRepositoryListing $listing, Learner $learner): Payment
    {
        abort_unless($parent->learners()->whereKey($learner->id)->exists(), 403, 'That child is not linked to your account.');

        if (! self::enabled()) {
            throw $this->refuse('Test payments are switched off.');
        }
        if (! self::ready()) {
            throw $this->refuse('Test payments are not set up yet. Nothing was charged.');
        }
        if ($listing->price_type !== 'Paid' || (float) $listing->price <= 0) {
            throw $this->refuse('This activity is free, so there is nothing to pay.');
        }
        if ($listing->isUnlockedFor($learner->id)) {
            throw $this->refuse('Already unlocked for '.$learner->first_name.'.');
        }

        // Two presses at the same moment (two tabs, a retry) must not make two checkouts: the check and the insert run one at a time for this child and activity.
        try {
            return Cache::lock('payment-start:'.$learner->id.':'.$listing->id, 30)->block(10, fn () => $this->begin($parent, $listing, $learner));
        } catch (LockTimeoutException) {
            throw $this->refuse('Another test payment for this activity is being started. Please wait a moment and try again.');
        }
    }

    /** The part of start() that must not run twice at the same moment for the same child and activity. */
    private function begin(ParentAccount $parent, OpenRepositoryListing $listing, Learner $learner): Payment
    {
        $open = Payment::where('learner_id', $learner->id)->where('listing_id', $listing->id)
            ->where('status', Payment::PENDING)->where('expires_at', '>', now())->whereNotNull('checkout_url')
            ->latest('id')->get();

        // The same parent coming back: the open checkout is used again.
        if ($mine = $open->firstWhere('parent_id', $parent->id)) {
            return $mine;
        }
        // Another guardian of the same child already has one open: a second payment would buy nothing, since the unlock is one per child and activity.
        if ($open->isNotEmpty()) {
            throw $this->refuse('A test payment for '.$learner->first_name.' and this activity is already open from another guardian. Please wait for it to finish.');
        }

        $payment = Payment::create([
            'reference' => $this->newReference(),
            'parent_id' => $parent->id,
            'learner_id' => $learner->id,
            'listing_id' => $listing->id,
            'provider' => 'paymongo',
            'amount_centavos' => (int) round((float) $listing->price * 100),
            'currency' => (string) config('payments.currency', 'PHP'),
            'status' => Payment::PENDING,
            'is_test' => true,
            'expires_at' => now()->addMinutes(max((int) config('payments.min_session_minutes', 5), (int) config('payments.session_minutes', 30))),
        ]);

        try {
            $session = $this->client->createCheckout([
                // Only the price, the activity title and a reference number. No name, no email, no learner code, nothing about the child.
                'line_items' => [[
                    'name' => Str::limit((string) $listing->activity->title, (int) config('payments.title_limit', 80), ''),
                    'amount' => $payment->amount_centavos,
                    'currency' => $payment->currency,
                    'quantity' => 1,
                ]],
                'payment_method_types' => array_values((array) config('payments.paymongo.methods')),
                'success_url' => route('parent.payments.back', $payment->reference),
                'cancel_url' => route('parent.payments.cancelled', $payment->reference),
                'reference_number' => $payment->reference,
                'description' => 'TaraBasa AI shared activity (test mode)',
                'send_email_receipt' => false,
                'show_line_items' => true,
            ]);
        } catch (PaymentProviderException $e) {
            Log::warning('A test payment could not be started: '.$e->getMessage());
            $payment->update(['status' => Payment::FAILED, 'failure_reason' => Str::limit('Could not be started. '.$e->getMessage(), 155, '')]);

            throw $this->refuse('The test payment could not be started right now. Nothing was charged. Please try again in a moment.');
        }

        try {
            $payment->update(['provider_session_id' => $session['id'], 'checkout_url' => $session['url']]);
        } catch (UniqueConstraintViolationException) {
            // The provider named a checkout that we already hold for another payment. That should never happen, and a crash is the wrong answer to it.
            Log::warning('The payment provider gave a checkout id that is already in use.');
            // The model still holds the duplicate id as unsaved, so the record is updated directly.
            Payment::whereKey($payment->id)->update(['status' => Payment::FAILED, 'failure_reason' => 'Could not be started. The provider named a checkout that is already in use.']);

            throw $this->refuse('The test payment could not be started right now. Nothing was charged. Please try again in a moment.');
        }

        return $payment;
    }

    // ------------------------------------------------------------------------------------------------------------- what the parent sees

    /**
     * Ask the provider whether a pending payment was paid (the parent has just come back, or the page is checking), and close it when its time is up.
     * A provider that cannot be reached leaves the payment as it was: the signed message may still arrive.
     */
    public function refresh(Payment $payment): Payment
    {
        if (! $payment->isPending()) {
            return $payment;
        }

        if ($payment->provider_session_id && self::ready()) {
            try {
                $session = $this->client->retrieveCheckout($payment->provider_session_id);
                $paid = $this->paidEntry((array) ($session['attributes'] ?? []));
                if ($paid) {
                    $this->applyPaid($payment, $paid['amount'], $paid['currency'], $paid['method'], $paid['id']);
                }
            } catch (PaymentProviderException $e) {
                Log::info('A test payment could not be checked: '.$e->getMessage());
            }
        }

        return $this->expireIfDue($payment->refresh());
    }

    /** The parent came back through "cancel". We look first (they may have paid in another tab), then close it. */
    public function cancel(Payment $payment): Payment
    {
        $payment = $this->refresh($payment);

        if ($payment->isPending()) {
            $payment->update(['status' => Payment::CANCELLED]);
            $this->closeAtProvider($payment);
        }

        return $payment->refresh();
    }

    /** Time is up: PayMongo never closes a checkout and sends no message, so TaraBasa does. */
    public function expireIfDue(Payment $payment): Payment
    {
        if ($payment->isPending() && $payment->expires_at && $payment->expires_at->isPast()) {
            $payment->update(['status' => Payment::EXPIRED]);
            $this->closeAtProvider($payment);
        }

        return $payment->refresh();
    }

    /**
     * This parent's attempts, newest first. A pending one whose time is up is closed on the way, but at most `history_close_limit` per visit:
     * closing means a request to the provider, and a page must never wait on many of them. The rest are closed on a later visit.
     */
    public function history(ParentAccount $parent): Collection
    {
        $rows = Payment::with(['listing.activity', 'learner'])->where('parent_id', $parent->id)->latest('id')->limit((int) config('payments.history_limit', 50))->get();

        $closed = 0;
        $cap = (int) config('payments.history_close_limit', 3);
        foreach ($rows as $payment) {
            if ($closed < $cap && $payment->isPending() && $payment->expires_at && $payment->expires_at->isPast()) {
                $this->expireIfDue($payment);
                $closed++;
            }
        }

        return $rows;
    }

    // ------------------------------------------------------------------------------------------------------------- the provider's message

    /**
     * What the provider's signed message does. Returns the HTTP status to answer with (PayMongo wants 200 to 209 and a JSON body, and tries a failed
     * delivery again) and a short word for what happened. Nothing from the body is trusted before the signature is checked.
     *
     * @return array{status: int, outcome: string}
     */
    public function handleWebhook(string $rawBody, ?string $signatureHeader): array
    {
        // Only a missing secret is "not ready". A genuine paid message is still applied when the switch has been turned off since the parent paid:
        // answering 503 would lose that unlock and, after repeated failures, PayMongo would disable the webhook.
        $secret = (string) config('payments.paymongo.webhook_secret');
        if ($secret === '') {
            return ['status' => 503, 'outcome' => 'not_ready'];
        }
        if (! PaymongoSignature::validTest($rawBody, $signatureHeader, $secret)) {
            Log::info('A payment message was refused: the signature did not match.');

            return ['status' => 401, 'outcome' => 'bad_signature'];
        }

        $json = json_decode($rawBody, true);
        if (! is_array($json)) {
            return ['status' => 400, 'outcome' => 'bad_body'];
        }

        $event = $this->readEvent($json);
        if ($event['live'] === true) {
            return ['status' => 200, 'outcome' => 'live_mode_ignored'];
        }
        if ($event['type'] !== 'checkout_session.payment.paid') {
            return ['status' => 200, 'outcome' => 'ignored'];
        }

        $attributes = (array) ($event['session']['attributes'] ?? []);
        $sessionId = (string) ($event['session']['id'] ?? '');
        $reference = (string) ($attributes['reference_number'] ?? '');

        $payment = $reference !== '' ? Payment::where('reference', $reference)->first() : null;
        $payment ??= $sessionId !== '' ? Payment::where('provider_session_id', $sessionId)->first() : null;
        if (! $payment) {
            return ['status' => 200, 'outcome' => 'unknown_payment'];
        }
        if ($sessionId !== '' && $payment->provider_session_id && $payment->provider_session_id !== $sessionId) {
            Log::warning('A payment message named a different checkout than the one we made.');

            return ['status' => 200, 'outcome' => 'session_mismatch'];
        }

        $paid = $this->paidEntry($attributes);
        if (! $paid) {
            return ['status' => 200, 'outcome' => 'no_paid_payment'];
        }

        return ['status' => 200, 'outcome' => $this->applyPaid($payment, $paid['amount'], $paid['currency'], $paid['method'], $paid['id']) ? 'paid' : 'amount_mismatch'];
    }

    /**
     * Save the unlock, once. The amount and the currency must be exactly what we asked for. A payment that was marked cancelled or expired is
     * accepted as paid, because the money really moved. Pressed twice, or the same message twice, the second changes nothing.
     */
    public function applyPaid(Payment $payment, int $amount, string $currency, ?string $method, ?string $providerPaymentId): bool
    {
        if ($amount !== $payment->amount_centavos || strtoupper($currency) !== strtoupper($payment->currency)) {
            Log::warning('A payment message had the wrong amount and was ignored.');

            return false;
        }

        return DB::transaction(function () use ($payment, $method, $providerPaymentId): bool {
            $fresh = Payment::whereKey($payment->id)->lockForUpdate()->first();
            if (! $fresh) {
                return false;
            }
            if ($fresh->isPaid()) {
                return true;
            }

            $fresh->update([
                'status' => Payment::PAID,
                'paid_at' => now(),
                'method' => $this->normalMethod($method),
                'provider_payment_id' => $providerPaymentId ? Str::limit($providerPaymentId, 78, '') : null,
                'failure_reason' => null,
            ]);

            $this->repository->unlockPaid($fresh->parentAccount, $fresh->listing, $fresh->learner, $fresh->pesos());

            return true;
        });
    }

    // ------------------------------------------------------------------------------------------------------------- small helpers

    /**
     * The provider's message comes in two shapes in its own documentation (read 2026-11-20): the events page wraps the body as
     * data.attributes.{type, livemode, data}, and the hosted checkout guide as data.{type, livemode, data}. Both are read; a real delivery decides.
     *
     * @return array{type: string, live: ?bool, session: array<string, mixed>}
     */
    private function readEvent(array $json): array
    {
        $data = (array) ($json['data'] ?? []);
        $inner = (array) ($data['attributes'] ?? []);

        $type = (string) ($inner['type'] ?? '');
        if ($type === '' && isset($data['type']) && $data['type'] !== 'event') {
            $type = (string) $data['type'];
        }

        $live = $inner['livemode'] ?? $data['livemode'] ?? null;
        $session = $inner['data'] ?? $data['data'] ?? [];

        return ['type' => $type, 'live' => is_bool($live) ? $live : null, 'session' => is_array($session) ? $session : []];
    }

    /** The one entry in the session's payments that is paid: its id, amount in centavos, currency and way of paying. */
    private function paidEntry(array $attributes): ?array
    {
        foreach ((array) ($attributes['payments'] ?? []) as $entry) {
            $a = (array) ($entry['attributes'] ?? []);
            if (($a['status'] ?? null) === 'paid') {
                return [
                    'id' => (string) ($entry['id'] ?? ''),
                    'amount' => (int) ($a['amount'] ?? -1),
                    'currency' => (string) ($a['currency'] ?? ''),
                    'method' => isset($a['source']['type']) ? (string) $a['source']['type'] : null,
                ];
            }
        }

        return null;
    }

    private function normalMethod(?string $method): ?string
    {
        return match (strtolower((string) $method)) {
            'card' => 'card',
            'gcash' => 'gcash',
            'paymaya', 'maya' => 'maya',
            '' => null,
            default => Str::limit(strtolower((string) $method), 18, ''),
        };
    }

    private function closeAtProvider(Payment $payment): void
    {
        if (! $payment->provider_session_id || ! self::ready()) {
            return;
        }

        try {
            $this->client->expireCheckout($payment->provider_session_id);
        } catch (PaymentProviderException $e) {
            // Already closed, already paid, or unreachable: none of these changes what we show.
            Log::info('A checkout was not closed: '.$e->getMessage());
        }
    }

    private function newReference(): string
    {
        do {
            $reference = 'TB-T-'.strtoupper(Str::random(8));
        } while (Payment::where('reference', $reference)->exists());

        return $reference;
    }

    private function refuse(string $message): ValidationException
    {
        return ValidationException::withMessages(['unlock' => $message]);
    }
}

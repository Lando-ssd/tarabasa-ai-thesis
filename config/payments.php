<?php

/**
 * Test mode payments for a paid activity in the Open Repository (a parent unlocks it for one child).
 *
 * THE SWITCH IS OFF BY DEFAULT. While it is off, a paid unlock is the old simulated step ("This is a simulated payment, so nothing is
 * charged") and nothing leaves the site. Turn it on with PAYMENTS_ENABLED=true in the hosting settings only after the wording of the
 * legal pages has been read (docs/legal/LEGAL-RISK-REGISTER.md, O16) and a test key has been put there.
 *
 * TEST MODE ONLY. This version refuses to work with a live key: the key must start with `sk_test_` (App\Services\PaymentService::ready),
 * and a message that says it came from live mode is ignored. Real money needs a registered business, taxes, fees and a new Refund Policy,
 * and is a separate decision (see the legal register).
 *
 * The provider is PayMongo (a Philippine provider), used through its hosted checkout page: our server asks it for a checkout address, the
 * parent is sent there, pays or cancels, and PayMongo tells our server the result with a signed message. TaraBasa never sees a card or a
 * wallet number, and sends only the price, the activity title and a reference number (no name, no email, no learner code).
 *
 * What was read in PayMongo's own documentation on 2026-11-20 and what was NOT is written in docs/HISTORY.md, "Test payments". In short:
 * not read, so unconfirmed until a real test delivery: the page that describes retrieving a Checkout Session, the exact webhook body (the
 * documentation shows two shapes, both are accepted), the Maya name in `payment_method_types`, and the lowest amount a checkout accepts.
 *
 * The keys are read from the environment and are never written in a file, printed, logged or sent anywhere except to PayMongo.
 */
return [
    'enabled' => (bool) env('PAYMENTS_ENABLED', false),

    'provider' => 'paymongo',

    'currency' => 'PHP',

    // How long a checkout stays open before TaraBasa closes it itself. PayMongo does not close a Checkout Session on its own and sends no
    // message when one expires, so the closing is ours. Maya gives 30 minutes to approve and QR Ph 30 by default (PayMongo's key concepts page).
    'session_minutes' => (int) env('PAYMENTS_SESSION_MINUTES', 30),

    'paymongo' => [
        // The secret key (starts with sk_test_). Only the hosting settings hold it.
        'secret_key' => env('PAYMONGO_SECRET_KEY'),

        // The webhook's own secret (starts with whsk_), shown when the webhook is made in the PayMongo dashboard. It signs every message.
        'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),

        'base_url' => env('PAYMONGO_BASE_URL', 'https://api.paymongo.com'),

        // /v1 creates the Payment Intent up front. PayMongo recommends /v2 for new integrations, and its pages for creating and retrieving a
        // session were not readable, so v1 (whose expire page was read) is used for all three calls. Change it here after a real test.
        'api_version' => env('PAYMONGO_API_VERSION', 'v1'),

        // The ways to pay shown on the provider's page. "paymaya" is the name the documentation's payment samples use for Maya; the create page
        // that lists the allowed names was not readable, so check it in the real test. QR Ph is left out on purpose: its test codes are REAL.
        'methods' => array_values(array_filter(array_map('trim', explode(',', (string) env('PAYMONGO_METHODS', 'card,gcash,paymaya'))))),
    ],
];

<?php

/**
 * The legal pages (Privacy Policy, Terms of Use, Cookie Policy, Refund Policy, Parent and Guardian Consent).
 *
 * `version` is written down with every consent a person gives (consent_records.policy_version), so we can always say
 * WHICH wording someone agreed to. Change it whenever the meaning of a legal page changes, not for a typo, and add a
 * row to the "Changes" table at the end of the Privacy Policy and the Terms of Use in the same commit.
 *
 * These pages must say only what the app really does. `tests/Feature/LegalPagesTest.php` pins the claims that matter
 * (no third party requests, only the cookies listed, recordings not stored, payments simulated). If the app starts
 * doing something new with data, change the page in the same commit.
 */
return [
    'version' => '2026-10-21.2',
    'updated' => '21 October 2026',

    // Who is responsible. Printed on every page. Set LEGAL_OPERATOR_DETAIL (for example the school or university and a
    // postal address) as soon as the team knows what it may print; until then the pages say "the student team".
    'operator' => env('LEGAL_OPERATOR', 'the TaraBasa AI student team'),
    'operator_detail' => env('LEGAL_OPERATOR_DETAIL'),

    // The address people write to about their information (the Data Privacy Act asks for a contact). Same variable the
    // Privacy page always used.
    'contact_email' => env('PRIVACY_CONTACT_EMAIL'),

    // The person accountable for compliance (Data Privacy Act, Section 21(b)), if the team names one.
    'accountable_person' => env('LEGAL_ACCOUNTABLE_PERSON'),

    // How many days we take to answer a request about someone's information.
    'reply_days' => 30,

    // The phone app can send the consent tick boxes from build 0.5.5. Until every phone has that build, the server
    // accepts a sign up without them (the website always requires them). Set API_CONSENT_REQUIRED=true once 0.5.5 is out.
    'api_consent_required' => (bool) env('API_CONSENT_REQUIRED', false),

    // The list shown on the left of every legal page.
    'docs' => [
        'privacy' => ['title' => 'Privacy Policy', 'route' => 'privacy'],
        'terms' => ['title' => 'Terms of Use', 'route' => 'terms'],
        'cookies' => ['title' => 'Cookie Policy', 'route' => 'cookies'],
        'refunds' => ['title' => 'Refund Policy', 'route' => 'refunds'],
        'consent' => ['title' => 'Parent and Guardian Consent', 'route' => 'consent'],
    ],
];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Teammate's deployed gemini_activity_gen service (Module 2 — Activity
    // Generation). Env var names match the ones the service's own README
    // recommends for a PHP/Laravel consumer.
    'activity_ai' => [
        'url' => env('ACTIVITY_AI_URL'),
        'key' => env('ACTIVITY_AI_KEY'),
    ],

    // Teammate's deployed Reading-api (Vosk-based). No key here on
    // purpose — the real deployed service has no authentication of any
    // kind (confirmed by reading its main.py directly), unlike
    // activity_ai's X-App-Key. The user is handling this gap with the
    // teammate outside of this codebase.
    'reading_ai' => [
        'url' => env('READING_AI_URL'),
    ],

    // Teammate's deployed Adaptive_Recommendator (deterministic, stateless
    // next-competency/difficulty decision service). Requires X-App-Key,
    // same pattern as activity_ai.
    'adaptive_recommender' => [
        'url' => env('ADAPTIVE_RECOMMENDER_URL'),
        'key' => env('ADAPTIVE_RECOMMENDER_KEY'),
    ],

];

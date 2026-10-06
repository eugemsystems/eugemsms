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

    // Book B FIN-05: Pesepay payment gateway. The keys come from the environment (never committed);
    // a school may instead store its own pair on its payment_gateways row. The method codes are
    // Pesepay's own payment-method identifiers and must be confirmed against the merchant account.
    'pesepay' => [
        'integration_key' => env('PESEPAY_INTEGRATION_KEY', ''),
        'encryption_key' => env('PESEPAY_ENCRYPTION_KEY', ''),
        'base_url' => env('PESEPAY_BASE_URL', 'https://api.pesepay.com/api/payments-engine'),
        'result_url' => env('PESEPAY_RESULT_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/api/v1/webhooks/payments/pesepay'),
        'return_url' => env('PESEPAY_RETURN_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/payments/return'),
        'method_codes' => [
            'ecocash' => ['USD' => env('PESEPAY_CODE_ECOCASH_USD', 'PZW211'), 'ZWG' => env('PESEPAY_CODE_ECOCASH_ZWG', 'PZW201')],
        ],
    ],

    // Book J SAA-02 §3, BR-SAA-02-001. Modules\Core\Http\Middleware\EnsureVendorGuard
    // enforces this list of exact IP addresses once it's non-empty — empty
    // (the default) means this environment hasn't configured one yet.
    'vendor' => [
        'ip_allowlist' => array_filter(explode(',', (string) env('VENDOR_IP_ALLOWLIST', ''))),
    ],

];

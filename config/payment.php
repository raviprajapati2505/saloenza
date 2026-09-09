<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Payment driver
    |--------------------------------------------------------------------------
    |
    | manual   — offline collection; platform admin approves upgrades
    | razorpay — online checkout when key_id + key_secret are configured
    | stripe   — Stripe Checkout when secret key is configured
    |
    */
    'driver' => env('PAYMENT_DRIVER', 'manual'),

    /*
    | When true, tenants never see payment links. Paid plan changes become
    | offline upgrade requests for platform admin approval after payment.
    */
    'offline_only' => filter_var(env('PAYMENT_OFFLINE_ONLY', true), FILTER_VALIDATE_BOOL),

    'currency' => env('PAYMENT_CURRENCY', 'INR'),

    'allow_instant_upgrade' => filter_var(
        env('PAYMENT_ALLOW_INSTANT_UPGRADE', env('APP_ENV') !== 'production'),
        FILTER_VALIDATE_BOOL,
    ),

    'manual' => [
        'allow_tenant_requests' => filter_var(
            env('PAYMENT_MANUAL_ALLOW_TENANT_REQUESTS', true),
            FILTER_VALIDATE_BOOL,
        ),
        'instructions' => env(
            'PAYMENT_MANUAL_INSTRUCTIONS',
            'Submit an upgrade request. Our team will confirm after offline payment (cash, UPI, or bank transfer).',
        ),
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
        'company_name' => env('RAZORPAY_COMPANY_NAME', env('APP_NAME', 'SalonOS')),
    ],

    'stripe' => [
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'secret_key' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'company_name' => env('STRIPE_COMPANY_NAME', env('APP_NAME', 'SalonOS')),
        'success_url' => env('STRIPE_SUCCESS_URL', env('APP_URL', 'http://localhost:8000').'/billing'),
        'cancel_url' => env('STRIPE_CANCEL_URL', env('APP_URL', 'http://localhost:8000').'/billing'),
    ],
];

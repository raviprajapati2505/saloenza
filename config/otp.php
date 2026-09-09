<?php

return [
    'code_length' => 4,
    'expiry_minutes' => (int) env('OTP_CODE_EXPIRY_MINUTES', 10),
    'allow_development_master_code' => filter_var(
        env('OTP_ALLOW_DEVELOPMENT_MASTER_CODE', env('APP_ENV') !== 'production'),
        FILTER_VALIDATE_BOOL,
    ),
    'development_master_code' => env('OTP_DEVELOPMENT_MASTER_CODE', '0101'),
    'expose_code_in_response' => filter_var(
        env('OTP_EXPOSE_CODE_IN_RESPONSE', env('APP_ENV') !== 'production'),
        FILTER_VALIDATE_BOOL,
    ),
    'channels' => [
        'email' => [
            'driver' => env('OTP_EMAIL_DRIVER', env('MAIL_MAILER', 'log') === 'log' ? 'log' : 'mail'),
        ],
        'sms' => [
            'driver' => env('OTP_SMS_DRIVER', 'log'),
            'sender_id' => env('OTP_AWS_SMS_SENDER_ID'),
            'type' => env('OTP_AWS_SMS_TYPE', 'Transactional'),
        ],
    ],
    'aws' => [
        'region' => env('OTP_AWS_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
        'version' => 'latest',
        'credentials' => [
            'key' => env('OTP_AWS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
            'secret' => env('OTP_AWS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
        ],
        'ses' => [
            'from_email' => env('OTP_AWS_SES_FROM_EMAIL', env('MAIL_FROM_ADDRESS', 'hello@example.com')),
            'from_name' => env('OTP_AWS_SES_FROM_NAME', env('MAIL_FROM_NAME', env('APP_NAME', 'Laravel'))),
        ],
    ],
];

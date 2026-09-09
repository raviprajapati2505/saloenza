<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tenant (Salon) Configuration Groups
    |--------------------------------------------------------------------------
    |
    | Defines settings salon owners can configure for their portal.
    | Resolution order: salon_settings → platform_settings → default below.
    |
    */
    'groups' => [
        'branding' => [
            'label' => 'Branding & Portal Identity',
            'description' => 'Customize how your salon portal appears to staff and customers.',
            'platform_manageable' => true,
            'settings' => [
                'portal_name' => [
                    'label' => 'Portal Display Name',
                    'description' => 'Shown in the header, emails, and customer-facing pages.',
                    'type' => 'string',
                    'default' => env('APP_NAME', 'Glowsuite'),
                ],
                'logo_path' => [
                    'label' => 'Logo',
                    'description' => 'Upload your salon logo (PNG/JPG, max 2 MB). Leave empty to use platform default.',
                    'type' => 'file',
                    'default' => null,
                ],
                'primary_color' => [
                    'label' => 'Primary Brand Color',
                    'description' => 'Used for buttons, accents, and highlights.',
                    'type' => 'color',
                    'default' => '#E0229A',
                ],
                'secondary_color' => [
                    'label' => 'Secondary Brand Color',
                    'description' => 'Used for gradients and secondary accents.',
                    'type' => 'color',
                    'default' => '#9125CA',
                ],
                'support_email' => [
                    'label' => 'Support Email',
                    'description' => 'Contact email shown to your staff and on receipts.',
                    'type' => 'string',
                    'default' => env('MAIL_FROM_ADDRESS', 'support@glowsuite.com'),
                ],
                'support_phone' => [
                    'label' => 'Support Phone',
                    'description' => 'Optional support phone for your salon.',
                    'type' => 'string',
                    'default' => '',
                ],
            ],
        ],
        'email' => [
            'label' => 'Email Delivery',
            'description' => 'Configure outbound email for appointment confirmations, reminders, and receipts.',
            'platform_manageable' => true,
            'settings' => [
                'use_custom' => [
                    'label' => 'Use Custom Email Settings',
                    'description' => 'For a salon, use that salon\'s SMTP instead of workspace defaults. Workspace SMTP is used automatically whenever an SMTP host is saved.',
                    'type' => 'boolean',
                    'default' => false,
                ],
                'from_name' => [
                    'label' => 'From Name',
                    'description' => 'Sender name recipients see in their inbox.',
                    'type' => 'string',
                    'default' => env('MAIL_FROM_NAME', env('APP_NAME', 'Glowsuite')),
                ],
                'from_email' => [
                    'label' => 'From Email',
                    'description' => 'Must be a verified sender address.',
                    'type' => 'string',
                    'default' => env('MAIL_FROM_ADDRESS', 'hello@glowsuite.com'),
                ],
                'reply_to' => [
                    'label' => 'Reply-To Email',
                    'description' => 'Optional reply address for customer responses.',
                    'type' => 'string',
                    'default' => '',
                ],
                'smtp_host' => [
                    'label' => 'SMTP Host',
                    'type' => 'string',
                    'default' => env('MAIL_HOST', ''),
                ],
                'smtp_port' => [
                    'label' => 'SMTP Port',
                    'type' => 'number',
                    'default' => (int) env('MAIL_PORT', 587),
                    'min' => 1,
                    'max' => 65535,
                    'integer' => true,
                ],
                'smtp_encryption' => [
                    'label' => 'Encryption',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'tls', 'label' => 'TLS'],
                        ['value' => 'ssl', 'label' => 'SSL'],
                        ['value' => 'none', 'label' => 'None'],
                    ],
                    'default' => env('MAIL_ENCRYPTION', 'tls') ?: 'tls',
                ],
                'smtp_username' => [
                    'label' => 'SMTP Username',
                    'type' => 'string',
                    'default' => env('MAIL_USERNAME', ''),
                ],
                'smtp_password' => [
                    'label' => 'SMTP Password',
                    'description' => 'Stored encrypted. Leave blank to keep existing password.',
                    'type' => 'secret',
                    'default' => env('MAIL_PASSWORD', ''),
                ],
            ],
        ],
        'sms' => [
            'label' => 'SMS & Text Messages',
            'description' => 'Configure SMS delivery for OTP and appointment reminders.',
            'platform_manageable' => true,
            'settings' => [
                'use_custom' => [
                    'label' => 'Use Custom SMS Settings',
                    'description' => 'When disabled, platform default SMS delivery is used.',
                    'type' => 'boolean',
                    'default' => false,
                ],
                'provider' => [
                    'label' => 'SMS Provider',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'platform', 'label' => 'Platform Default'],
                        ['value' => 'aws_sns', 'label' => 'AWS SNS'],
                        ['value' => 'msg91', 'label' => 'MSG91'],
                        ['value' => 'twilio', 'label' => 'Twilio'],
                    ],
                    'default' => 'platform',
                ],
                'sender_id' => [
                    'label' => 'Sender ID / From Number',
                    'description' => 'Alphanumeric sender ID (MSG91/AWS) or Twilio phone number.',
                    'type' => 'string',
                    'default' => env('OTP_AWS_SMS_SENDER_ID', 'GLOWSUITE'),
                ],
                'account_sid' => [
                    'label' => 'Twilio Account SID',
                    'description' => 'Required when using Twilio as SMS provider.',
                    'type' => 'string',
                    'default' => '',
                ],
                'api_key' => [
                    'label' => 'API Key / Auth Token',
                    'description' => 'Stored encrypted. Leave blank to keep existing key.',
                    'type' => 'secret',
                    'default' => '',
                ],
            ],
        ],
        'notifications' => [
            'label' => 'Notification Preferences',
            'description' => 'Control which automated messages are sent to your customers and staff.',
            'platform_manageable' => true,
            'settings' => [
                'appointment_confirmation' => [
                    'label' => 'Appointment Confirmation',
                    'description' => 'Send confirmation when a booking is created.',
                    'type' => 'boolean',
                    'default' => true,
                ],
                'appointment_reminder' => [
                    'label' => 'Appointment Reminder',
                    'description' => 'Send reminder before scheduled appointments.',
                    'type' => 'boolean',
                    'default' => true,
                ],
                'reminder_hours_before' => [
                    'label' => 'Reminder Lead Time (hours)',
                    'type' => 'number',
                    'default' => 24,
                    'min' => 1,
                    'max' => 168,
                    'integer' => true,
                ],
                'booking_cancellation' => [
                    'label' => 'Cancellation Notice',
                    'description' => 'Notify customers when an appointment is cancelled.',
                    'type' => 'boolean',
                    'default' => true,
                ],
                'staff_assignment_alert' => [
                    'label' => 'Staff Assignment Alerts',
                    'description' => 'Notify staff when assigned to new appointments.',
                    'type' => 'boolean',
                    'default' => true,
                ],
                'subscription_renewal_reminder' => [
                    'label' => 'Subscription Renewal Reminders',
                    'description' => 'Email salon owner before plan renewal.',
                    'type' => 'boolean',
                    'default' => true,
                ],
                'payment_outstanding_reminder' => [
                    'label' => 'Outstanding Payment Reminders',
                    'description' => 'Automatically remind customers after a bill stays unpaid past the overdue duration. Manual reminders from a visit still work when this is off.',
                    'type' => 'boolean',
                    'default' => false,
                ],
                'outstanding_overdue_days' => [
                    'label' => 'Payment Overdue After (days)',
                    'description' => 'Days after the visit before an unpaid bill is treated as overdue and eligible for automatic reminders.',
                    'type' => 'number',
                    'default' => 3,
                    'min' => 1,
                    'max' => 90,
                    'integer' => true,
                ],
                'birthday_wishes' => [
                    'label' => 'Birthday Wish Emails',
                    'description' => 'Email customers on their birthday with a greeting and your salon offer.',
                    'type' => 'boolean',
                    'default' => true,
                ],
                'anniversary_wishes' => [
                    'label' => 'Anniversary Wish Emails',
                    'description' => 'Email customers on their anniversary with a greeting and your salon offer.',
                    'type' => 'boolean',
                    'default' => true,
                ],
                'birthday_offer_text' => [
                    'label' => 'Birthday Offer',
                    'description' => 'Standard promotional line included in birthday emails. Leave blank to send wishes only.',
                    'type' => 'textarea',
                    'default' => 'Celebrate with 15% off any service this week — show this email at the front desk.',
                ],
                'anniversary_offer_text' => [
                    'label' => 'Anniversary Offer',
                    'description' => 'Standard promotional line included in anniversary emails. Leave blank to send wishes only.',
                    'type' => 'textarea',
                    'default' => 'Enjoy 15% off a couple’s or signature service this week — show this email at the front desk.',
                ],
            ],
        ],
        'regional' => [
            'label' => 'Regional & Locale',
            'description' => 'Timezone, currency, and formatting for your salon operations.',
            'platform_manageable' => true,
            'settings' => [
                'timezone' => [
                    'label' => 'Timezone',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'Asia/Kolkata', 'label' => 'India (IST)'],
                        ['value' => 'Asia/Dubai', 'label' => 'UAE (GST)'],
                        ['value' => 'Asia/Singapore', 'label' => 'Singapore (SGT)'],
                        ['value' => 'Europe/London', 'label' => 'United Kingdom (GMT/BST)'],
                        ['value' => 'America/New_York', 'label' => 'US Eastern'],
                        ['value' => 'America/Los_Angeles', 'label' => 'US Pacific'],
                    ],
                    'default' => env('APP_TIMEZONE', 'Asia/Kolkata'),
                ],
                'locale' => [
                    'label' => 'Language / Locale',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'en_IN', 'label' => 'English (India)'],
                        ['value' => 'en_US', 'label' => 'English (US)'],
                        ['value' => 'en_GB', 'label' => 'English (UK)'],
                    ],
                    'default' => 'en_IN',
                ],
                'currency' => [
                    'label' => 'Currency Code',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'INR', 'label' => 'INR (₹)'],
                        ['value' => 'USD', 'label' => 'USD ($)'],
                        ['value' => 'AED', 'label' => 'AED (د.إ)'],
                        ['value' => 'GBP', 'label' => 'GBP (£)'],
                    ],
                    'default' => 'INR',
                ],
                'date_format' => [
                    'label' => 'Date Format',
                    'type' => 'select',
                    'options' => [
                        ['value' => 'd M Y', 'label' => '31 Jan 2026'],
                        ['value' => 'M d, Y', 'label' => 'Jan 31, 2026'],
                        ['value' => 'Y-m-d', 'label' => '2026-01-31'],
                    ],
                    'default' => 'd M Y',
                ],
                'time_format' => [
                    'label' => 'Time Format',
                    'type' => 'select',
                    'options' => [
                        ['value' => '12h', 'label' => '12-hour (2:30 PM)'],
                        ['value' => '24h', 'label' => '24-hour (14:30)'],
                    ],
                    'default' => '12h',
                ],
            ],
        ],
        'invoice' => [
            'label' => 'Invoices & Receipts',
            'description' => 'Customize billing documents issued to your customers.',
            'platform_manageable' => true,
            'settings' => [
                'invoice_prefix' => [
                    'label' => 'Invoice Prefix',
                    'description' => 'Prefix for invoice numbers (e.g. INV-).',
                    'type' => 'string',
                    'default' => 'INV-',
                ],
                'tax_label' => [
                    'label' => 'Tax Label',
                    'description' => 'Label shown for tax line items (e.g. GST).',
                    'type' => 'string',
                    'default' => 'GST',
                ],
                'footer_note' => [
                    'label' => 'Footer Note',
                    'description' => 'Optional message printed at the bottom of receipts.',
                    'type' => 'textarea',
                    'default' => 'Thank you for visiting us!',
                ],
                'show_gst_number' => [
                    'label' => 'Show GST Number on Receipts',
                    'type' => 'boolean',
                    'default' => true,
                ],
            ],
        ],
    ],
];

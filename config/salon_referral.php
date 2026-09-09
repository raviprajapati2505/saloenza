<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy fallback (deprecated)
    |--------------------------------------------------------------------------
    |
    | Salon referral settings are managed in the database via platform_settings
    | and editable at /admin/settings by super admins.
    |
    */
    'commission_rate' => (float) env('SALON_REFERRAL_COMMISSION_RATE', 10),
    'qualifying_months' => (int) env('SALON_REFERRAL_QUALIFYING_MONTHS', 6),
];

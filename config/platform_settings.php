<?php

return [
    'groups' => [
        'salon_referrals' => [
            'label' => 'Salon Referral Program',
            'description' => 'Configure credits earned when salon owners refer other salons.',
            'settings' => [
                'commission_rate' => [
                    'label' => 'Commission Rate (%)',
                    'description' => 'Percentage of the referred salon subscription value credited after qualification.',
                    'type' => 'number',
                    'default' => 10,
                    'min' => 0,
                    'max' => 100,
                    'step' => 0.01,
                ],
                'qualifying_months' => [
                    'label' => 'Qualifying Period (months)',
                    'description' => 'Number of active subscription months before referral credit is issued.',
                    'type' => 'number',
                    'default' => 6,
                    'min' => 1,
                    'max' => 36,
                    'step' => 1,
                    'integer' => true,
                ],
            ],
        ],
    ],
];

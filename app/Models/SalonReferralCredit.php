<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalonReferralCredit extends Model
{
    public const STATUS_CREDITED = 'credited';

    protected $fillable = [
        'referrer_saloon_id',
        'salon_referral_id',
        'referred_saloon_id',
        'saloon_subscription_id',
        'status',
        'currency',
        'base_amount',
        'commission_rate',
        'credit_amount',
        'credited_at',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'credit_amount' => 'decimal:2',
            'credited_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function referrerSaloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class, 'referrer_saloon_id');
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(SalonReferral::class, 'salon_referral_id');
    }

    public function referredSaloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class, 'referred_saloon_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SaloonSubscription::class, 'saloon_subscription_id');
    }
}

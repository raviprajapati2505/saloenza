<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffiliateCommission extends Model
{
    public const TYPE_ONBOARDING = 'onboarding';

    public const TYPE_RENEWAL = 'renewal';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_REQUESTED = 'requested';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'affiliate_partner_id',
        'affiliate_referral_id',
        'saloon_id',
        'subscription_upgrade_order_id',
        'saloon_subscription_id',
        'affiliate_withdrawal_request_id',
        'type',
        'status',
        'currency',
        'base_amount',
        'commission_rate',
        'commission_amount',
        'locked_until',
        'available_at',
        'released_at',
        'withdrawn_at',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'base_amount' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'locked_until' => 'datetime',
            'available_at' => 'datetime',
            'released_at' => 'datetime',
            'withdrawn_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function affiliatePartner(): BelongsTo
    {
        return $this->belongsTo(AffiliatePartner::class);
    }

    public function referral(): BelongsTo
    {
        return $this->belongsTo(AffiliateReferral::class, 'affiliate_referral_id');
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function upgradeOrder(): BelongsTo
    {
        return $this->belongsTo(SubscriptionUpgradeOrder::class, 'subscription_upgrade_order_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SaloonSubscription::class, 'saloon_subscription_id');
    }

    public function withdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(AffiliateWithdrawalRequest::class, 'affiliate_withdrawal_request_id');
    }
}

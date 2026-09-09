<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliatePartner extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'code',
        'display_name',
        'status',
        'onboarding_commission_rate',
        'renewal_commission_rate',
        'commission_lock_days',
        'payout_method',
        'payout_details',
        'notes',
        'joined_at',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'onboarding_commission_rate' => 'decimal:2',
            'renewal_commission_rate' => 'decimal:2',
            'commission_lock_days' => 'integer',
            'payout_details' => 'array',
            'joined_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function saloons(): HasMany
    {
        return $this->hasMany(Saloon::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(AffiliateWithdrawalRequest::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
}

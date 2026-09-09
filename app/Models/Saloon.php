<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Saloon extends Model
{
    protected $fillable = [
        'name',
        'branch_name',
        'address',
        'city',
        'state',
        'phone',
        'whatsapp',
        'gst_number',
        'working_hours',
        'payment_type',
        'payment_amount',
        'transaction_id',
        'affiliate_partner_id',
        'affiliate_referral_code',
        'affiliate_attributed_at',
        'is_active',
        'activation_status',
        'referral_code',
        'referrer_saloon_id',
        'owner_referred_at',
    ];

    public const ACTIVATION_ACTIVE = 'active';

    public const ACTIVATION_PENDING = 'pending_activation';

    protected $casts = [
        'working_hours' => 'array',
        'payment_amount' => 'decimal:2',
        'affiliate_attributed_at' => 'datetime',
        'owner_referred_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function isActivationPending(): bool
    {
        return $this->activation_status === self::ACTIVATION_PENDING;
    }

    public function markActivationPending(): void
    {
        $this->forceFill([
            'is_active' => false,
            'activation_status' => self::ACTIVATION_PENDING,
        ])->save();
    }

    public function markActivated(): void
    {
        $this->forceFill([
            'is_active' => true,
            'activation_status' => self::ACTIVATION_ACTIVE,
        ])->save();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(SaloonBranch::class);
    }

    public function affiliatePartner(): BelongsTo
    {
        return $this->belongsTo(AffiliatePartner::class);
    }

    public function affiliateReferral(): HasOne
    {
        return $this->hasOne(AffiliateReferral::class);
    }

    public function referrerSaloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class, 'referrer_saloon_id');
    }

    public function salonReferralsMade(): HasMany
    {
        return $this->hasMany(SalonReferral::class, 'referrer_saloon_id');
    }

    public function salonReferralReceived(): HasOne
    {
        return $this->hasOne(SalonReferral::class, 'referred_saloon_id');
    }

    public function salonReferralCredits(): HasMany
    {
        return $this->hasMany(SalonReferralCredit::class, 'referrer_saloon_id');
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'saloon_customers')
            ->withTimestamps();
    }

    public function salonServiceProducts(): HasMany
    {
        return $this->hasMany(SalonServiceProduct::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(SaloonSubscription::class);
    }

    public function subscriptionHistories(): HasMany
    {
        return $this->hasMany(SaloonSubscriptionHistory::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(SaloonSubscription::class)
            ->whereIn('status', [SaloonSubscription::STATUS_ACTIVE, SaloonSubscription::STATUS_TRIALING])
            ->latest('id');
    }

    public function settings(): HasMany
    {
        return $this->hasMany(SalonSetting::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionUpgradeOrder extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REJECTED = 'rejected';

    public const CHANNEL_MANUAL = 'manual';

    public const CHANNEL_RAZORPAY = 'razorpay';

    public const CHANNEL_STRIPE = 'stripe';

    protected $fillable = [
        'saloon_id',
        'requested_by_user_id',
        'approved_by_user_id',
        'from_subscription_plan_id',
        'to_subscription_plan_id',
        'amount',
        'currency',
        'channel',
        'status',
        'payment_type',
        'transaction_id',
        'notes',
        'gateway_order_id',
        'gateway_payment_id',
        'gateway_payload',
        'paid_at',
        'fulfilled_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_payload' => 'array',
        'paid_at' => 'datetime',
        'fulfilled_at' => 'datetime',
    ];

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'from_subscription_plan_id');
    }

    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'to_subscription_plan_id');
    }

    public function isPendingManual(): bool
    {
        return $this->channel === self::CHANNEL_MANUAL && $this->status === self::STATUS_PENDING;
    }

    public function isAwaitingPayment(): bool
    {
        return $this->status === self::STATUS_AWAITING_PAYMENT;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaloonSubscriptionHistory extends Model
{
    public const ACTION_ASSIGNED = 'assigned';

    public const ACTION_CANCELLED = 'cancelled';

    public const ACTION_EXPIRED = 'expired';

    public const ACTION_RENEWED = 'renewed';

    public const ACTION_TRIAL_STARTED = 'trial_started';

    protected $fillable = [
        'saloon_id',
        'saloon_subscription_id',
        'subscription_plan_id',
        'from_subscription_plan_id',
        'subscription_upgrade_order_id',
        'changed_by_user_id',
        'action',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'cancelled_at',
        'notes',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SaloonSubscription::class, 'saloon_subscription_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'from_subscription_plan_id');
    }

    public function upgradeOrder(): BelongsTo
    {
        return $this->belongsTo(SubscriptionUpgradeOrder::class, 'subscription_upgrade_order_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}

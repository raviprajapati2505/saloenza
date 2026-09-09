<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaloonSubscription extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'saloon_id',
        'subscription_plan_id',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'cancelled_at',
        'renewal_reminder_sent_at',
        'renewal_reminder_days_sent',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'renewal_reminder_sent_at' => 'datetime',
            'renewal_reminder_days_sent' => 'array',
        ];
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(SaloonSubscriptionHistory::class);
    }

    /**
     * Date used for renewal / expiry reminders (trial end or paid period end).
     */
    public function renewalDueAt(): ?\Illuminate\Support\Carbon
    {
        if ($this->status === self::STATUS_TRIALING && $this->trial_ends_at !== null) {
            return $this->trial_ends_at;
        }

        return $this->ends_at;
    }

    public function isCurrentlyActive(): bool
    {
        if (! in_array($this->status, [self::STATUS_ACTIVE, self::STATUS_TRIALING], true)) {
            return false;
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return false;
        }

        if ($this->status === self::STATUS_TRIALING && $this->trial_ends_at !== null && $this->trial_ends_at->isPast()) {
            return false;
        }

        return true;
    }
}

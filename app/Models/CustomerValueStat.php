<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerValueStat extends Model
{
    public const TIERS = ['platinum', 'gold', 'silver', 'bronze'];

    public const LAPSE_STATUSES = ['active', 'at_risk', 'lapsed', 'lost'];

    protected $fillable = [
        'customer_id',
        'saloon_id',
        'lifetime_spend',
        'visit_count',
        'avg_ticket',
        'first_visit_at',
        'last_visit_at',
        'avg_days_between_visits',
        'expected_annual_value',
        'clv_score',
        'clv_tier',
        'churn_risk_score',
        'lapse_status',
        'lapse_status_updated_at',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'lifetime_spend' => 'decimal:2',
            'avg_ticket' => 'decimal:2',
            'avg_days_between_visits' => 'decimal:2',
            'expected_annual_value' => 'decimal:2',
            'clv_score' => 'integer',
            'churn_risk_score' => 'integer',
            'visit_count' => 'integer',
            'first_visit_at' => 'datetime',
            'last_visit_at' => 'datetime',
            'lapse_status_updated_at' => 'datetime',
            'computed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }
}

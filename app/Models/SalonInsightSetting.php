<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalonInsightSetting extends Model
{
    protected $fillable = [
        'saloon_id',
        'lapse_days_threshold',
        'high_value_spend_threshold',
        'imbalance_variance_pct',
        'lookback_days',
        'recovery_rate_assumption',
        'enabled_insight_types',
    ];

    protected function casts(): array
    {
        return [
            'lapse_days_threshold' => 'integer',
            'high_value_spend_threshold' => 'decimal:2',
            'imbalance_variance_pct' => 'integer',
            'lookback_days' => 'integer',
            'recovery_rate_assumption' => 'decimal:4',
            'enabled_insight_types' => 'array',
        ];
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }
}

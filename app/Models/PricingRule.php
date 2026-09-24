<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends Model
{
    protected $fillable = [
        'saloon_id',
        'branch_id',
        'name',
        'is_active',
        'priority',
        'adjustment_type',
        'adjustment_value',
        'service_ids',
        'category_ids',
        'staff_ids',
        'days_of_week',
        'time_start',
        'time_end',
        'date_from',
        'date_to',
        'min_lead_hours',
        'channel',
        'stackable',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'adjustment_value' => 'decimal:2',
            'service_ids' => 'array',
            'category_ids' => 'array',
            'staff_ids' => 'array',
            'days_of_week' => 'array',
            'date_from' => 'date',
            'date_to' => 'date',
            'min_lead_hours' => 'integer',
            'stackable' => 'boolean',
        ];
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SaloonBranch::class, 'branch_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RetentionPolicy extends Model
{
    protected $fillable = [
        'saloon_id',
        'name',
        'at_risk_after_days',
        'lapsed_after_days',
        'lost_after_days',
        'min_visits_required',
        'exclude_tag_ids',
        'channels_allowed',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'at_risk_after_days' => 'integer',
            'lapsed_after_days' => 'integer',
            'lost_after_days' => 'integer',
            'min_visits_required' => 'integer',
            'exclude_tag_ids' => 'array',
            'channels_allowed' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function cohorts(): HasMany
    {
        return $this->hasMany(RetentionCohort::class, 'policy_id');
    }
}

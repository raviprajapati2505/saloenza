<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerSegment extends Model
{
    public const TYPE_STATIC = 'static';

    public const TYPE_DYNAMIC = 'dynamic';

    protected $fillable = [
        'saloon_id',
        'name',
        'type',
        'rules_json',
        'estimated_size',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rules_json' => 'array',
            'estimated_size' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MarketingCampaign::class, 'segment_id');
    }

    public function scopeForSaloon(Builder $query, int $saloonId): Builder
    {
        return $query->where('saloon_id', $saloonId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

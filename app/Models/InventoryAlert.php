<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAlert extends Model
{
    public const TYPE_STOCKOUT = 'stockout';

    public const TYPE_STOCKOUT_RISK = 'stockout_risk';

    public const TYPE_SLOW_MOVER = 'slow_mover';

    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    protected $fillable = [
        'saloon_id',
        'branch_id',
        'product_id',
        'type',
        'severity',
        'message',
        'status',
        'metrics_json',
    ];

    protected function casts(): array
    {
        return [
            'metrics_json' => 'array',
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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OPEN);
    }

    public function scopeForSaloon(Builder $query, int $saloonId): Builder
    {
        return $query->where('saloon_id', $saloonId);
    }
}

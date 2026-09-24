<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionPayoutPeriod extends Model
{
    protected $fillable = [
        'saloon_id',
        'period_start',
        'period_end',
        'status',
        'total_commission',
        'locked_at',
        'locked_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'total_commission' => 'decimal:2',
            'locked_at' => 'datetime',
        ];
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function locker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(CommissionLineItem::class, 'payout_period_id');
    }
}

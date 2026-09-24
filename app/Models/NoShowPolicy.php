<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NoShowPolicy extends Model
{
    protected $fillable = [
        'saloon_id',
        'is_enabled',
        'apply_mode',
        'min_no_shows',
        'window_days',
        'protection_type',
        'deposit_type',
        'deposit_value',
        'fee_type',
        'fee_value',
        'cancel_cutoff_hours',
        'currency',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'min_no_shows' => 'integer',
            'window_days' => 'integer',
            'deposit_value' => 'decimal:2',
            'fee_value' => 'decimal:2',
            'cancel_cutoff_hours' => 'integer',
        ];
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }
}

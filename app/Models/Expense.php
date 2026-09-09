<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    protected $fillable = [
        'saloon_id',
        'branch_id',
        'category',
        'title',
        'amount',
        'incurred_on',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'incurred_on' => 'date',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Salon-wide overheads belong to every branch view, so a branch filter keeps them.
     */
    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        if ($branchId === null) {
            return $query;
        }

        return $query->where(
            fn (Builder $inner) => $inner->whereNull('branch_id')->orWhere('branch_id', $branchId),
        );
    }

    public function scopeIncurredBetween(Builder $query, string $from, string $to): Builder
    {
        return $query->whereDate('incurred_on', '>=', $from)->whereDate('incurred_on', '<=', $to);
    }
}

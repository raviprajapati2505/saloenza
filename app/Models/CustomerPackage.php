<?php

namespace App\Models;

use App\Support\Package\CustomerPackageStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPackage extends Model
{
    protected $fillable = [
        'saloon_id',
        'branch_id',
        'customer_id',
        'package_id',
        'purchased_at',
        'expires_at',
        'status',
        'amount_paid',
        'payment_method',
        'payment_ref',
        'sold_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'purchased_at' => 'datetime',
            'expires_at' => 'datetime',
            'amount_paid' => 'decimal:2',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sold_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(CustomerPackageItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CustomerPackageStatus::ACTIVE);
    }

    public function isRedeemable(): bool
    {
        if ($this->status !== CustomerPackageStatus::ACTIVE) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }
}

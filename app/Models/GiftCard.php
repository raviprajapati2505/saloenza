<?php

namespace App\Models;

use App\Support\GiftCard\GiftCardStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GiftCard extends Model
{
    protected $fillable = [
        'saloon_id',
        'branch_id',
        'code',
        'initial_balance',
        'current_balance',
        'currency',
        'status',
        'purchaser_customer_id',
        'recipient_name',
        'recipient_phone',
        'issued_at',
        'expires_at',
        'sold_appointment_id',
        'issued_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'initial_balance' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
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

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'purchaser_customer_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function soldAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'sold_appointment_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(GiftCardTransaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', GiftCardStatus::ACTIVE);
    }

    public function isRedeemable(): bool
    {
        if ($this->status !== GiftCardStatus::ACTIVE) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return (float) $this->current_balance > 0;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionLineItem extends Model
{
    protected $fillable = [
        'saloon_id',
        'branch_id',
        'user_id',
        'appointment_id',
        'appointment_service_id',
        'appointment_product_id',
        'rule_id',
        'basis_amount',
        'calc_type',
        'rate_applied',
        'commission_amount',
        'status',
        'exclusion_reason',
        'earned_on',
        'payout_period_id',
    ];

    protected function casts(): array
    {
        return [
            'basis_amount' => 'decimal:2',
            'rate_applied' => 'decimal:4',
            'commission_amount' => 'decimal:2',
            'earned_on' => 'date',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'rule_id');
    }

    public function payoutPeriod(): BelongsTo
    {
        return $this->belongsTo(CommissionPayoutPeriod::class, 'payout_period_id');
    }
}

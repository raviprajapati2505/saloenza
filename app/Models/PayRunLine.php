<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayRunLine extends Model
{
    protected $fillable = [
        'pay_run_id',
        'user_id',
        'branch_id',
        'basic_salary',
        'commission_total',
        'gross',
        'earnings_json',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'commission_total' => 'decimal:2',
            'gross' => 'decimal:2',
            'earnings_json' => 'array',
        ];
    }

    public function payRun(): BelongsTo
    {
        return $this->belongsTo(PayRun::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SaloonBranch::class, 'branch_id');
    }
}

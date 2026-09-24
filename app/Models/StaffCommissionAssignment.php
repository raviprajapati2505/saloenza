<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffCommissionAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'saloon_id',
        'branch_id',
        'scheme_id',
        'effective_from',
        'effective_to',
        'override_percent',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'override_percent' => 'decimal:4',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SaloonBranch::class, 'branch_id');
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(CommissionScheme::class, 'scheme_id');
    }
}

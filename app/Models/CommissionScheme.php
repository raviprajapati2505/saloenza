<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionScheme extends Model
{
    protected $fillable = [
        'saloon_id',
        'name',
        'is_default',
        'is_active',
        'currency',
        'effective_from',
        'effective_to',
        'commission_on_no_show',
        'commission_when_payment_unpaid',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'commission_on_no_show' => 'boolean',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(CommissionRule::class, 'scheme_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StaffCommissionAssignment::class, 'scheme_id');
    }
}

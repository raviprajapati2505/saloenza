<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SaloonBranch extends Model
{
    protected $fillable = [
        'branch_name',
        'business_address_1',
        'business_address_2',
        'saloon_id',
        'city',
        'state',
        'area_pincode',
        'country',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'branch_id');
    }

    public function manager(): HasOne
    {
        return $this->hasOne(User::class, 'branch_id')
            ->whereHas('role', fn ($query) => $query->where('code', \App\Support\Role\RoleCodes::SALON_BRANCH_MANAGER))
            ->latestOfMany();
    }

    public function catalogItems(): HasMany
    {
        return $this->hasMany(SalonServiceProduct::class, 'branch_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CustomerTag extends Model
{
    protected $fillable = [
        'saloon_id',
        'name',
        'color',
    ];

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_customer_tag')
            ->withTimestamps();
    }

    public function scopeForSaloon(Builder $query, int $saloonId): Builder
    {
        return $query->where('saloon_id', $saloonId);
    }
}

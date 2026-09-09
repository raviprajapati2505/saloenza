<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'name',
        'sku',
        'brand',
        'unit',
        'category_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function serviceProducts(): HasMany
    {
        return $this->hasMany(ServiceProduct::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_products')
            ->withPivot('id', 'default_price')
            ->withTimestamps();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'name',
        'category_id',
        'default_price',
        'duration_minutes',
        'gender',
        'category_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'duration_minutes' => 'integer',
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

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'service_products')
            ->withPivot('id', 'default_price')
            ->withTimestamps();
    }
}

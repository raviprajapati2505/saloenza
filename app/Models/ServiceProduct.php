<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceProduct extends Model
{
    protected $fillable = [
        'service_id',
        'product_id',
        'default_price',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

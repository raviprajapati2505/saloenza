<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackageItem extends Model
{
    protected $fillable = [
        'package_id',
        'service_id',
        'quantity_total',
        'unit_value',
    ];

    protected function casts(): array
    {
        return [
            'quantity_total' => 'integer',
            'unit_value' => 'decimal:2',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}

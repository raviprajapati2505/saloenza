<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPackageItem extends Model
{
    protected $fillable = [
        'customer_package_id',
        'package_item_id',
        'service_id',
        'quantity_total',
        'quantity_remaining',
        'unit_value',
    ];

    protected function casts(): array
    {
        return [
            'quantity_total' => 'integer',
            'quantity_remaining' => 'integer',
            'unit_value' => 'decimal:2',
        ];
    }

    public function customerPackage(): BelongsTo
    {
        return $this->belongsTo(CustomerPackage::class);
    }

    public function packageItem(): BelongsTo
    {
        return $this->belongsTo(PackageItem::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}

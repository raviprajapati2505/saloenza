<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    protected $fillable = [
        'saloon_id',
        'branch_id',
        'product_id',
        'type',
        'quantity_change',
        'quantity_after',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity_change' => 'integer',
            'quantity_after' => 'integer',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SaloonBranch::class, 'branch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

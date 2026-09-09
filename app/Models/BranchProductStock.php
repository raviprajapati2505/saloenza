<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchProductStock extends Model
{
    protected $fillable = [
        'branch_id',
        'product_id',
        'supplier_id',
        'quantity_on_hand',
        'reorder_level',
        'cost_price',
        'selling_price',
        'max_stock',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'reorder_level' => 'integer',
            'max_stock' => 'integer',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SaloonBranch::class, 'branch_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

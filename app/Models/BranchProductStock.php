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
        'avg_daily_sales_7d',
        'avg_daily_sales_30d',
        'avg_daily_sales_90d',
        'days_of_cover',
        'days_to_stockout',
        'velocity_class',
        'suggested_reorder_qty',
        'metrics_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'reorder_level' => 'integer',
            'max_stock' => 'integer',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'avg_daily_sales_7d' => 'decimal:4',
            'avg_daily_sales_30d' => 'decimal:4',
            'avg_daily_sales_90d' => 'decimal:4',
            'days_of_cover' => 'decimal:2',
            'days_to_stockout' => 'decimal:2',
            'suggested_reorder_qty' => 'integer',
            'metrics_updated_at' => 'datetime',
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

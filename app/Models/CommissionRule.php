<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    protected $fillable = [
        'scheme_id',
        'priority',
        'name',
        'applies_to',
        'service_id',
        'category_id',
        'product_id',
        'staff_user_id',
        'role_id',
        'calc_type',
        'rate_value',
        'tier_json',
        'include_discounts',
        'min_line_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'rate_value' => 'decimal:4',
            'tier_json' => 'array',
            'include_discounts' => 'boolean',
            'min_line_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function scheme(): BelongsTo
    {
        return $this->belongsTo(CommissionScheme::class, 'scheme_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}

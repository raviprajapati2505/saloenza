<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentService extends Model
{
    protected $fillable = [
        'appointment_id',
        'service_id',
        'product_id',
        'staff_id',
        'is_staff_locked',
        'price',
        'list_price',
        'applied_pricing_rule_id',
        'quantity',
        'duration_minutes',
        'starts_at',
        'ends_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'list_price' => 'decimal:2',
            'duration_minutes' => 'integer',
            'is_staff_locked' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function appliedPricingRule(): BelongsTo
    {
        return $this->belongsTo(PricingRule::class, 'applied_pricing_rule_id');
    }
}

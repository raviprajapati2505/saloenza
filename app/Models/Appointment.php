<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    public const TYPE_APPOINTMENT = 'appointment';

    public const TYPE_WALK_IN = 'walk_in';

    /** Counter sale with no service lines — products only. */
    public const TYPE_PRODUCT_SALE = 'product_sale';

    public const TYPES = [
        self::TYPE_APPOINTMENT,
        self::TYPE_WALK_IN,
        self::TYPE_PRODUCT_SALE,
    ];

    public const SOURCE_INTERNAL = 'internal';

    public const SOURCE_POS = 'pos';

    public const SOURCE_WALK_IN = 'walk_in';

    public const SOURCE_SELF_BOOKING = 'self_booking';

    public const SOURCE_IMPORT = 'import';

    protected $fillable = [
        'saloon_id',
        'branch_id',
        'customer_id',
        'staff_id',
        'service_id',
        'product_id',
        'starts_at',
        'ends_at',
        'status',
        'type',
        'booking_source',
        'import_key',
        'price',
        'services_total',
        'products_total',
        'discount',
        'grand_total',
        'payment_status',
        'payment_method',
        'amount_paid',
        'paid_at',
        'deposit_required_amount',
        'deposit_status',
        'deposit_paid_at',
        'no_show_fee_amount',
        'no_show_fee_status',
        'invoice_number',
        'reminder_sent_at',
        'payment_reminder_sent_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'payment_reminder_sent_at' => 'datetime',
            'price' => 'decimal:2',
            'services_total' => 'decimal:2',
            'products_total' => 'decimal:2',
            'discount' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'paid_at' => 'datetime',
            'deposit_required_amount' => 'decimal:2',
            'deposit_paid_at' => 'datetime',
            'no_show_fee_amount' => 'decimal:2',
        ];
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SaloonBranch::class, 'branch_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function services(): HasMany
    {
        return $this->hasMany(AppointmentService::class)->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(AppointmentProduct::class)->orderBy('sort_order');
    }

    public function isWalkIn(): bool
    {
        return $this->type === self::TYPE_WALK_IN;
    }

    public function isProductSale(): bool
    {
        return $this->type === self::TYPE_PRODUCT_SALE;
    }

    /**
     * Counter sales and walk-ins settle immediately, so stock leaves the branch
     * as soon as they are completed.
     */
    public function isOverTheCounter(): bool
    {
        return $this->isWalkIn() || $this->isProductSale();
    }
}

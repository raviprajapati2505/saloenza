<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaitlistEntry extends Model
{
    protected $fillable = [
        'saloon_id',
        'branch_id',
        'customer_id',
        'service_id',
        'preferred_staff_id',
        'earliest_at',
        'latest_at',
        'preferred_days',
        'priority',
        'status',
        'notes',
        'created_by',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'earliest_at' => 'datetime',
            'latest_at' => 'datetime',
            'preferred_days' => 'array',
            'priority' => 'integer',
            'expires_at' => 'datetime',
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function preferredStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_staff_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(WaitlistOffer::class);
    }
}

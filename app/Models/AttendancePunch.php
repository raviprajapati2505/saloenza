<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePunch extends Model
{
    public const TYPE_IN = 'in';

    public const TYPE_OUT = 'out';

    protected $fillable = [
        'saloon_id',
        'branch_id',
        'user_id',
        'type',
        'punched_at',
        'method',
        'geo_lat',
        'geo_lng',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'geo_lat' => 'decimal:7',
            'geo_lng' => 'decimal:7',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

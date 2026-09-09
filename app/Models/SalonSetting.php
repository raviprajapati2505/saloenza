<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalonSetting extends Model
{
    protected $fillable = [
        'saloon_id',
        'group',
        'key',
        'value',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}

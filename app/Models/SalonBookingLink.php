<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SalonBookingLink extends Model
{
    protected $fillable = [
        'saloon_id',
        'branch_id',
        'token',
        'label',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::lower(Str::random(32));
        } while (self::query()->where('token', $token)->exists());

        return $token;
    }

    public function publicUrl(): string
    {
        return url('/book/'.$this->token);
    }
}

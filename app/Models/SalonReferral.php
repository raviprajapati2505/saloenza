<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SalonReferral extends Model
{
    public const SOURCE_LINK = 'link';

    public const SOURCE_ADMIN = 'admin';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_QUALIFIED = 'qualified';

    protected $fillable = [
        'referrer_saloon_id',
        'referred_saloon_id',
        'owner_user_id',
        'referral_code',
        'source',
        'status',
        'metadata',
        'referred_at',
        'qualified_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'referred_at' => 'datetime',
            'qualified_at' => 'datetime',
        ];
    }

    public function referrerSaloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class, 'referrer_saloon_id');
    }

    public function referredSaloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class, 'referred_saloon_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function credit(): HasOne
    {
        return $this->hasOne(SalonReferralCredit::class);
    }
}

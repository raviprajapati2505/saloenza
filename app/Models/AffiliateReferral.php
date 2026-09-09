<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateReferral extends Model
{
    public const SOURCE_LINK = 'link';

    public const SOURCE_DIRECT = 'direct';

    public const SOURCE_ADMIN = 'admin';

    protected $fillable = [
        'affiliate_partner_id',
        'saloon_id',
        'owner_user_id',
        'referral_code',
        'source',
        'metadata',
        'referred_at',
        'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'referred_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function affiliatePartner(): BelongsTo
    {
        return $this->belongsTo(AffiliatePartner::class);
    }

    public function saloon(): BelongsTo
    {
        return $this->belongsTo(Saloon::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }
}

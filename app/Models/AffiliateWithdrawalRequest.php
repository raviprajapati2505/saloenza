<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliateWithdrawalRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PAID = 'paid';

    protected $fillable = [
        'affiliate_partner_id',
        'amount',
        'currency',
        'status',
        'payout_reference',
        'notes',
        'requested_at',
        'reviewed_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function affiliatePartner(): BelongsTo
    {
        return $this->belongsTo(AffiliatePartner::class);
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'affiliate_withdrawal_request_id');
    }
}

<?php

namespace App\Http\Resources\Api\V1\Affiliate;

use App\Models\AffiliateWithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AffiliateWithdrawalRequest */
class AffiliateWithdrawalRequestResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'payout_reference' => $this->payout_reference,
            'notes' => $this->notes,
            'requested_at' => $this->requested_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'affiliate_partner' => $this->whenLoaded('affiliatePartner', fn () => $this->affiliatePartner ? [
                'id' => $this->affiliatePartner->id,
                'code' => $this->affiliatePartner->code,
                'display_name' => $this->affiliatePartner->display_name,
                'user' => $this->affiliatePartner->relationLoaded('user') && $this->affiliatePartner->user ? [
                    'id' => $this->affiliatePartner->user->id,
                    'name' => $this->affiliatePartner->user->name,
                    'email' => $this->affiliatePartner->user->email,
                ] : null,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

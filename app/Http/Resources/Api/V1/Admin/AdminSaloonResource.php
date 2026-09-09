<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\Saloon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Saloon */
class AdminSaloonResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, int|float|bool|string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'business_name' => $this->name,
            'payment_type' => $this->payment_type,
            'payment_amount' => $this->payment_amount,
            'transaction_id' => $this->transaction_id,
            'is_active' => (bool) $this->is_active,
            'referral_code' => $this->referral_code,
            'affiliate_partner_id' => $this->affiliate_partner_id,
            'affiliate_referral_code' => $this->affiliate_referral_code,
            'affiliate_attributed_at' => $this->affiliate_attributed_at?->toISOString(),
            'affiliate_partner' => $this->whenLoaded('affiliatePartner', fn () => $this->affiliatePartner ? [
                'id' => $this->affiliatePartner->id,
                'code' => $this->affiliatePartner->code,
                'display_name' => $this->affiliatePartner->display_name,
            ] : null),
        ];
    }
}

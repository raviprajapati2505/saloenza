<?php

namespace App\Http\Resources\Api\V1\Affiliate;

use App\Models\AffiliatePartner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AffiliatePartner */
class AffiliatePartnerResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'code' => $this->code,
            'display_name' => $this->display_name,
            'status' => $this->status,
            'onboarding_commission_rate' => (float) $this->onboarding_commission_rate,
            'renewal_commission_rate' => (float) $this->renewal_commission_rate,
            'commission_lock_days' => (int) $this->commission_lock_days,
            'payout_method' => $this->payout_method,
            'payout_details' => $this->payout_details,
            'signup_url' => url('/register?affiliate=' . urlencode($this->code)),
            'referrals_count' => $this->whenCounted('referrals'),
            'commissions_count' => $this->whenCounted('commissions'),
            'withdrawal_requests_count' => $this->whenCounted('withdrawalRequests'),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ] : null),
            'notes' => $this->notes,
            'joined_at' => $this->joined_at?->toISOString(),
            'activated_at' => $this->activated_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

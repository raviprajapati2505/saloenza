<?php

namespace App\Http\Resources\Api\V1\Affiliate;

use App\Models\AffiliateReferral;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AffiliateReferral */
class AffiliateReferralResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'referral_code' => $this->referral_code,
            'source' => $this->source,
            'referred_at' => $this->referred_at?->toISOString(),
            'converted_at' => $this->converted_at?->toISOString(),
            'saloon' => $this->whenLoaded('saloon', fn () => $this->saloon ? [
                'id' => $this->saloon->id,
                'name' => $this->saloon->name,
                'city' => $this->saloon->city,
                'state' => $this->saloon->state,
                'phone' => $this->saloon->phone,
                'whatsapp' => $this->saloon->whatsapp,
                'is_active' => (bool) $this->saloon->is_active,
                'activation_status' => $this->saloon->activation_status,
                'affiliate_attributed_at' => $this->saloon->affiliate_attributed_at?->toISOString(),
            ] : null),
            'owner' => $this->whenLoaded('owner', fn () => $this->owner ? [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
                'email' => $this->owner->email,
                'phone' => $this->owner->phone,
            ] : null),
            'subscription' => $this->when(
                $this->relationLoaded('saloon') && $this->saloon !== null,
                fn () => app(SubscriptionEntitlements::class)->salonSubscriptionSummary($this->saloon),
            ),
            'metadata' => $this->metadata,
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\Saloon;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Saloon */
class AdminOnboardingListItemResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User|null $owner */
        $owner = $this->getAttribute('owner_user');
        /** @var \App\Models\SaloonBranch|null $primaryBranch */
        $primaryBranch = $this->relationLoaded('branches') ? $this->branches->first() : null;

        return [
            'id' => $this->id,
            'business_name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'activation_status' => $this->activation_status ?: Saloon::ACTIVATION_ACTIVE,
            'activation_pending' => method_exists($this->resource, 'isActivationPending')
                ? $this->resource->isActivationPending()
                : false,
            'branch_name' => $primaryBranch?->branch_name ?? $this->branch_name,
            'city' => $primaryBranch?->city ?? $this->city,
            'state' => $primaryBranch?->state ?? $this->state,
            'phone' => $this->phone,
            'created_at' => $this->created_at?->toISOString(),
            'onboarding_status' => (string) ($this->getAttribute('onboarding_status') ?? 'no_owner'),
            'branch_count' => (int) ($this->getAttribute('branch_count') ?? 0),
            'owner' => $owner ? [
                'id' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->phone,
                'is_active' => (bool) $owner->is_active,
                'onboarding_completed_at' => $owner->onboarding_completed_at?->toISOString(),
                'should_onboard' => is_null($owner->onboarding_completed_at),
            ] : null,
            'affiliate_partner' => $this->whenLoaded('affiliatePartner', fn () => $this->affiliatePartner ? [
                'id' => $this->affiliatePartner->id,
                'code' => $this->affiliatePartner->code,
                'display_name' => $this->affiliatePartner->display_name,
            ] : null),
        ];
    }
}

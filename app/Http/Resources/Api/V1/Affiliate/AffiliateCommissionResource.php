<?php

namespace App\Http\Resources\Api\V1\Affiliate;

use App\Models\AffiliateCommission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AffiliateCommission */
class AffiliateCommissionResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'status' => $this->status,
            'currency' => $this->currency,
            'base_amount' => (float) $this->base_amount,
            'commission_rate' => (float) $this->commission_rate,
            'commission_amount' => (float) $this->commission_amount,
            'locked_until' => $this->locked_until?->toISOString(),
            'available_at' => $this->available_at?->toISOString(),
            'released_at' => $this->released_at?->toISOString(),
            'withdrawn_at' => $this->withdrawn_at?->toISOString(),
            'notes' => $this->notes,
            'saloon' => $this->whenLoaded('saloon', fn () => $this->saloon ? [
                'id' => $this->saloon->id,
                'name' => $this->saloon->name,
            ] : null),
            'upgrade_order' => $this->whenLoaded('upgradeOrder', fn () => $this->upgradeOrder ? [
                'id' => $this->upgradeOrder->id,
                'amount' => (float) $this->upgradeOrder->amount,
                'paid_at' => $this->upgradeOrder->paid_at?->toISOString(),
                'status' => $this->upgradeOrder->status,
            ] : null),
        ];
    }
}

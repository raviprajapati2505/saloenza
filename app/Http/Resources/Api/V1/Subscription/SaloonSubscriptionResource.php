<?php

namespace App\Http\Resources\Api\V1\Subscription;

use App\Models\SaloonSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SaloonSubscription */
class SaloonSubscriptionResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'trial_ends_at' => $this->trial_ends_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'plan' => $this->whenLoaded('plan', fn () => (new SubscriptionPlanResource($this->plan))->resolve()),
        ];
    }
}

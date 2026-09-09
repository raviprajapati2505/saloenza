<?php

namespace App\Http\Resources\Api\V1\Subscription;

use App\Models\SaloonSubscriptionHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SaloonSubscriptionHistory */
class SaloonSubscriptionHistoryResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'saloon_id' => $this->saloon_id,
            'saloon_name' => $this->whenLoaded('saloon', fn () => $this->saloon?->name),
            'saloon_subscription_id' => $this->saloon_subscription_id,
            'action' => $this->action,
            'status' => $this->status,
            'plan' => $this->whenLoaded('plan', fn () => $this->plan
                ? (new SubscriptionPlanResource($this->plan))->resolve()
                : null),
            'from_plan' => $this->whenLoaded('fromPlan', fn () => $this->fromPlan
                ? (new SubscriptionPlanResource($this->fromPlan))->resolve()
                : null),
            'subscription_upgrade_order_id' => $this->subscription_upgrade_order_id,
            'changed_by' => $this->whenLoaded('changedBy', fn () => $this->changedBy ? [
                'id' => $this->changedBy->id,
                'name' => $this->changedBy->name,
                'email' => $this->changedBy->email,
            ] : null),
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'trial_ends_at' => $this->trial_ends_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'notes' => $this->notes,
            'meta' => $this->meta,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

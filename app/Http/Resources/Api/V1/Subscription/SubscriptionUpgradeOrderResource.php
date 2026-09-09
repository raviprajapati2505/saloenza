<?php

namespace App\Http\Resources\Api\V1\Subscription;

use App\Models\SubscriptionUpgradeOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SubscriptionUpgradeOrder */
class SubscriptionUpgradeOrderResource extends JsonResource
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
            'status' => $this->status,
            'channel' => $this->channel,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'payment_type' => $this->payment_type,
            'transaction_id' => $this->transaction_id,
            'notes' => $this->notes,
            'gateway_order_id' => $this->gateway_order_id,
            'gateway_payment_id' => $this->gateway_payment_id,
            'from_plan' => $this->whenLoaded('fromPlan', fn () => $this->fromPlan
                ? (new SubscriptionPlanResource($this->fromPlan))->resolve()
                : null),
            'to_plan' => $this->whenLoaded('toPlan', fn () => $this->toPlan
                ? (new SubscriptionPlanResource($this->toPlan))->resolve()
                : null),
            'requested_by' => $this->whenLoaded('requestedBy', fn () => $this->requestedBy ? [
                'id' => $this->requestedBy->id,
                'name' => $this->requestedBy->name,
                'email' => $this->requestedBy->email,
            ] : null),
            'approved_by' => $this->whenLoaded('approvedBy', fn () => $this->approvedBy ? [
                'id' => $this->approvedBy->id,
                'name' => $this->approvedBy->name,
            ] : null),
            'paid_at' => $this->paid_at?->toISOString(),
            'fulfilled_at' => $this->fulfilled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1\Subscription;

use App\Models\SubscriptionPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SubscriptionPlan */
class SubscriptionPlanResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'billing_interval' => $this->billing_interval,
            'trial_days' => (int) $this->trial_days,
            'max_branches' => $this->max_branches,
            'max_staff' => $this->max_staff,
            'modules' => $this->moduleList(),
            'is_active' => (bool) $this->is_active,
            'is_public' => (bool) $this->is_public,
            'sort_order' => (int) $this->sort_order,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

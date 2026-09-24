<?php

namespace App\Http\Resources\Api\V1\Commission;

use App\Models\CommissionScheme;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommissionScheme */
class CommissionSchemeResource extends JsonResource
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
            'name' => $this->name,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'currency' => $this->currency,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'commission_on_no_show' => (bool) $this->commission_on_no_show,
            'commission_when_payment_unpaid' => $this->commission_when_payment_unpaid,
            'rules_count' => $this->when(
                isset($this->rules_count) || $this->relationLoaded('rules'),
                fn () => $this->rules_count ?? $this->rules->count(),
            ),
            'rules' => CommissionRuleResource::collection($this->whenLoaded('rules')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

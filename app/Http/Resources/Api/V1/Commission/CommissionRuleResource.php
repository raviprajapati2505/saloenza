<?php

namespace App\Http\Resources\Api\V1\Commission;

use App\Models\CommissionRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommissionRule */
class CommissionRuleResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'scheme_id' => $this->scheme_id,
            'priority' => (int) $this->priority,
            'name' => $this->name,
            'applies_to' => $this->applies_to,
            'service_id' => $this->service_id,
            'category_id' => $this->category_id,
            'product_id' => $this->product_id,
            'staff_user_id' => $this->staff_user_id,
            'role_id' => $this->role_id,
            'calc_type' => $this->calc_type,
            'rate_value' => $this->rate_value !== null ? (float) $this->rate_value : null,
            'tier_json' => $this->tier_json,
            'include_discounts' => (bool) $this->include_discounts,
            'min_line_price' => $this->min_line_price !== null ? (float) $this->min_line_price : null,
            'is_active' => (bool) $this->is_active,
            'service' => $this->whenLoaded('service', fn () => $this->service ? [
                'id' => $this->service->id,
                'name' => $this->service->name,
            ] : null),
            'product' => $this->whenLoaded('product', fn () => $this->product ? [
                'id' => $this->product->id,
                'name' => $this->product->name,
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ] : null),
            'staff_user' => $this->whenLoaded('staffUser', fn () => $this->staffUser ? [
                'id' => $this->staffUser->id,
                'name' => $this->staffUser->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

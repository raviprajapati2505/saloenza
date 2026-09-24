<?php

namespace App\Http\Resources\Api\V1\Pricing;

use App\Models\PricingRule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PricingRule */
class PricingRuleResource extends JsonResource
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
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'priority' => (int) $this->priority,
            'adjustment_type' => $this->adjustment_type,
            'adjustment_value' => $this->adjustment_value,
            'service_ids' => $this->service_ids ?? [],
            'category_ids' => $this->category_ids ?? [],
            'staff_ids' => $this->staff_ids ?? [],
            'days_of_week' => $this->days_of_week ?? [],
            'time_start' => $this->time_start,
            'time_end' => $this->time_end,
            'date_from' => $this->date_from?->toDateString(),
            'date_to' => $this->date_to?->toDateString(),
            'min_lead_hours' => $this->min_lead_hours,
            'channel' => $this->channel,
            'stackable' => (bool) $this->stackable,
            'created_at' => $this->created_at?->toISOString(),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
                'name' => $this->branch->branch_name,
            ] : null),
        ];
    }
}

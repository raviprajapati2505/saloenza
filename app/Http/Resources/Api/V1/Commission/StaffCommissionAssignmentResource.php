<?php

namespace App\Http\Resources\Api\V1\Commission;

use App\Models\StaffCommissionAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StaffCommissionAssignment */
class StaffCommissionAssignmentResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'saloon_id' => $this->saloon_id,
            'branch_id' => $this->branch_id,
            'scheme_id' => $this->scheme_id,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_to' => $this->effective_to?->toDateString(),
            'override_percent' => $this->override_percent !== null ? (float) $this->override_percent : null,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'commission_rate' => $this->user->commission_rate !== null
                    ? (float) $this->user->commission_rate
                    : null,
            ] : null),
            'scheme' => $this->whenLoaded('scheme', fn () => $this->scheme ? [
                'id' => $this->scheme->id,
                'name' => $this->scheme->name,
                'is_active' => (bool) $this->scheme->is_active,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Appointment */
class CustomerVisitResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $serviceName = $this->relationLoaded('service') ? $this->service?->name : null;
        if ($this->relationLoaded('services') && $this->services->isNotEmpty()) {
            $serviceName = $this->services
                ->map(fn ($line) => $line->relationLoaded('service') ? $line->service?->name : null)
                ->filter()
                ->unique()
                ->implode(', ');
        }

        return [
            'id' => $this->id,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'status' => $this->status,
            'type' => $this->type ?? Appointment::TYPE_APPOINTMENT,
            'grand_total' => $this->grand_total,
            'service_name' => $serviceName,
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->branch_name,
            ] : null),
            'staff' => $this->whenLoaded('staff', fn () => $this->staff ? [
                'id' => $this->staff->id,
                'name' => $this->staff->name,
            ] : null),
        ];
    }
}

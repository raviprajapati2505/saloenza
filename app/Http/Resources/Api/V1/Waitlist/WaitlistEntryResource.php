<?php

namespace App\Http\Resources\Api\V1\Waitlist;

use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WaitlistEntry */
class WaitlistEntryResource extends JsonResource
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
            'customer_id' => $this->customer_id,
            'service_id' => $this->service_id,
            'preferred_staff_id' => $this->preferred_staff_id,
            'earliest_at' => $this->earliest_at?->toISOString(),
            'latest_at' => $this->latest_at?->toISOString(),
            'preferred_days' => $this->preferred_days,
            'priority' => (int) $this->priority,
            'status' => $this->status,
            'notes' => $this->notes,
            'expires_at' => $this->expires_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
                'email' => $this->customer->email,
            ] : null),
            'service' => $this->whenLoaded('service', fn () => $this->service ? [
                'id' => $this->service->id,
                'name' => $this->service->name,
            ] : null),
            'preferred_staff' => $this->whenLoaded('preferredStaff', fn () => $this->preferredStaff ? [
                'id' => $this->preferredStaff->id,
                'name' => $this->preferredStaff->name,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
                'name' => $this->branch->branch_name,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'offers' => $this->whenLoaded('offers', fn () => WaitlistOfferResource::collection($this->offers)->resolve()),
        ];
    }
}

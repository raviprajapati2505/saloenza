<?php

namespace App\Http\Resources\Api\V1\Waitlist;

use App\Models\WaitlistOffer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WaitlistOffer */
class WaitlistOfferResource extends JsonResource
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
            'waitlist_entry_id' => $this->waitlist_entry_id,
            'source_appointment_id' => $this->source_appointment_id,
            'slot_starts_at' => $this->slot_starts_at?->toISOString(),
            'slot_ends_at' => $this->slot_ends_at?->toISOString(),
            'staff_id' => $this->staff_id,
            'service_id' => $this->service_id,
            'branch_id' => $this->branch_id,
            'status' => $this->status,
            'channel' => $this->channel,
            'offered_at' => $this->offered_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'response_appointment_id' => $this->response_appointment_id,
            'entry' => $this->whenLoaded('entry', fn () => $this->entry
                ? (new WaitlistEntryResource($this->entry))->resolve()
                : null),
            'staff' => $this->whenLoaded('staff', fn () => $this->staff ? [
                'id' => $this->staff->id,
                'name' => $this->staff->name,
            ] : null),
            'service' => $this->whenLoaded('service', fn () => $this->service ? [
                'id' => $this->service->id,
                'name' => $this->service->name,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
                'name' => $this->branch->branch_name,
            ] : null),
        ];
    }
}

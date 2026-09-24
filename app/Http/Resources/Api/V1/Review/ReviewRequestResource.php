<?php

namespace App\Http\Resources\Api\V1\Review;

use App\Models\ReviewRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReviewRequest */
class ReviewRequestResource extends JsonResource
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
            'appointment_id' => $this->appointment_id,
            'status' => $this->status,
            'channel' => $this->channel,
            'google_link' => $this->google_link,
            'google_link_eligible' => (bool) $this->google_link_eligible,
            'scheduled_for' => $this->scheduled_for?->toISOString(),
            'sent_at' => $this->sent_at?->toISOString(),
            'suppress_reason' => $this->suppress_reason,
            'created_at' => $this->created_at?->toISOString(),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone ?? null,
                'email' => $this->customer->email ?? null,
            ] : null),
            'appointment' => $this->whenLoaded('appointment', fn () => $this->appointment ? [
                'id' => $this->appointment->id,
                'starts_at' => $this->appointment->starts_at?->toISOString(),
                'status' => $this->appointment->status,
            ] : null),
            'rating' => $this->whenLoaded('rating', fn () => $this->rating ? [
                'id' => $this->rating->id,
                'rating' => $this->rating->rating,
                'comment' => $this->rating->comment,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
        ];
    }
}

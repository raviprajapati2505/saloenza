<?php

namespace App\Http\Resources\Api\V1\Review;

use App\Models\ServiceRating;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ServiceRating */
class ServiceRatingResource extends JsonResource
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
            'appointment_id' => $this->appointment_id,
            'customer_id' => $this->customer_id,
            'review_request_id' => $this->review_request_id,
            'staff_user_id' => $this->staff_user_id,
            'rating' => $this->rating,
            'comment' => $this->comment,
            'shared_publicly' => (bool) $this->shared_publicly,
            'created_at' => $this->created_at?->toISOString(),
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ] : null),
            'staff' => $this->whenLoaded('staff', fn () => $this->staff ? [
                'id' => $this->staff->id,
                'name' => $this->staff->name,
            ] : null),
        ];
    }
}

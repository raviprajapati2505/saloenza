<?php

namespace App\Http\Resources\Api\V1\Attendance;

use App\Models\AttendancePunch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin AttendancePunch */
class AttendancePunchResource extends JsonResource
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
            'user_id' => $this->user_id,
            'type' => $this->type,
            'punched_at' => $this->punched_at?->toISOString(),
            'method' => $this->method,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
            ] : null),
        ];
    }
}

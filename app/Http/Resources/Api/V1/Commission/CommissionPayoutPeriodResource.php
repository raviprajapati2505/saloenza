<?php

namespace App\Http\Resources\Api\V1\Commission;

use App\Models\CommissionPayoutPeriod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CommissionPayoutPeriod */
class CommissionPayoutPeriodResource extends JsonResource
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
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'status' => $this->status,
            'total_commission' => $this->total_commission !== null ? (float) $this->total_commission : 0,
            'locked_at' => $this->locked_at?->toISOString(),
            'locked_by' => $this->locked_by,
            'notes' => $this->notes,
            'locker' => $this->whenLoaded('locker', fn () => $this->locker ? [
                'id' => $this->locker->id,
                'name' => $this->locker->name,
            ] : null),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

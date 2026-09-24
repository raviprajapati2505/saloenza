<?php

namespace App\Http\Resources\Api\V1\NoShow;

use App\Models\NoShowPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin NoShowPolicy */
class NoShowPolicyResource extends JsonResource
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
            'is_enabled' => (bool) $this->is_enabled,
            'apply_mode' => $this->apply_mode,
            'min_no_shows' => (int) $this->min_no_shows,
            'window_days' => (int) $this->window_days,
            'protection_type' => $this->protection_type,
            'deposit_type' => $this->deposit_type,
            'deposit_value' => $this->deposit_value,
            'fee_type' => $this->fee_type,
            'fee_value' => $this->fee_value,
            'cancel_cutoff_hours' => (int) $this->cancel_cutoff_hours,
            'currency' => $this->currency,
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

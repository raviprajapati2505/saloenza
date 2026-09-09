<?php

namespace App\Http\Resources\Api\V1\Saloon;

use App\Models\Saloon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Saloon */
class SaloonResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'business_name' => $this->name,
            'payment_type' => $this->payment_type,
            'payment_amount' => $this->payment_amount,
            'transaction_id' => $this->transaction_id,
            'is_active' => (bool) $this->is_active,
            'referral_code' => $this->referral_code,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

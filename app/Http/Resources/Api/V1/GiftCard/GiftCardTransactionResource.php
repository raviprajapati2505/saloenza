<?php

namespace App\Http\Resources\Api\V1\GiftCard;

use App\Models\GiftCardTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GiftCardTransaction */
class GiftCardTransactionResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'gift_card_id' => $this->gift_card_id,
            'type' => $this->type,
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'appointment_id' => $this->appointment_id,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toISOString(),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
        ];
    }
}

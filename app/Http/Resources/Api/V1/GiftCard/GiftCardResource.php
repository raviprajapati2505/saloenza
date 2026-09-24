<?php

namespace App\Http\Resources\Api\V1\GiftCard;

use App\Models\GiftCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin GiftCard */
class GiftCardResource extends JsonResource
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
            'code' => $this->code,
            'initial_balance' => $this->initial_balance,
            'current_balance' => $this->current_balance,
            'currency' => $this->currency,
            'status' => $this->status,
            'purchaser_customer_id' => $this->purchaser_customer_id,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'issued_at' => $this->issued_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'purchaser' => $this->whenLoaded('purchaser', fn () => $this->purchaser ? [
                'id' => $this->purchaser->id,
                'name' => $this->purchaser->name,
                'phone' => $this->purchaser->phone,
            ] : null),
            'issuer' => $this->whenLoaded('issuer', fn () => $this->issuer ? [
                'id' => $this->issuer->id,
                'name' => $this->issuer->name,
            ] : null),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
            ] : null),
            'transactions' => $this->whenLoaded('transactions', fn () => $this->transactions->map(
                fn ($tx) => (new GiftCardTransactionResource($tx))->resolve(),
            )->values()->all()),
        ];
    }
}

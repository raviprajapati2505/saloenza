<?php

namespace App\Http\Resources\Api\V1\Appointment;

use App\Models\Appointment;
use App\Support\Customer\CustomerContactPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Appointment */
class AppointmentResource extends JsonResource
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
            'staff_id' => $this->staff_id,
            'service_id' => $this->service_id,
            'product_id' => $this->product_id,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'date' => $this->starts_at?->toDateString(),
            'status' => $this->status,
            'type' => $this->type ?? Appointment::TYPE_APPOINTMENT,
            'booking_source' => $this->booking_source,
            'price' => $this->price,
            'services_total' => $this->services_total,
            'products_total' => $this->products_total,
            'discount' => $this->discount,
            'grand_total' => $this->grand_total,
            'payment_status' => $this->payment_status ?? 'unpaid',
            'payment_method' => $this->payment_method,
            'amount_paid' => $this->amount_paid,
            'balance_due' => max(round((float) ($this->grand_total ?? 0) - (float) ($this->amount_paid ?? 0), 2), 0),
            'paid_at' => $this->paid_at?->toISOString(),
            'deposit_required_amount' => $this->deposit_required_amount,
            'deposit_status' => $this->deposit_status ?? 'not_required',
            'deposit_paid_at' => $this->deposit_paid_at?->toISOString(),
            'no_show_fee_amount' => $this->no_show_fee_amount,
            'no_show_fee_status' => $this->no_show_fee_status ?? 'n/a',
            'invoice_number' => $this->invoice_number,
            'payment_reminder_sent_at' => $this->payment_reminder_sent_at?->toISOString(),
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toISOString(),
            'customer' => $this->whenLoaded('customer', fn () => CustomerContactPayload::identity(
                $this->customer,
                $request->user(),
            )),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'name' => $this->branch->branch_name,
                'branch_name' => $this->branch->branch_name,
            ] : null),
            'staff' => $this->whenLoaded('staff', fn () => [
                'id' => $this->staff?->id,
                'name' => $this->staff?->name,
            ]),
            'service' => $this->whenLoaded('service', fn () => [
                'id' => $this->service?->id,
                'name' => $this->service?->name,
            ]),
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product?->id,
                'name' => $this->product?->name,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator?->id,
                'name' => $this->creator?->name,
            ]),
            'services' => $this->whenLoaded('services', fn () => $this->services->map(fn ($line) => [
                'id' => $line->id,
                'service_id' => $line->service_id,
                'product_id' => $line->product_id,
                'staff_id' => $line->staff_id,
                'is_staff_locked' => (bool) $line->is_staff_locked,
                'price' => $line->price,
                'list_price' => $line->list_price,
                'applied_pricing_rule_id' => $line->applied_pricing_rule_id,
                'quantity' => (int) ($line->quantity ?? 1),
                'duration_minutes' => $line->duration_minutes,
                'starts_at' => $line->starts_at?->toISOString(),
                'ends_at' => $line->ends_at?->toISOString(),
                'sort_order' => $line->sort_order,
                'service' => $line->relationLoaded('service') ? [
                    'id' => $line->service?->id,
                    'name' => $line->service?->name,
                ] : null,
                'product' => $line->relationLoaded('product') ? [
                    'id' => $line->product?->id,
                    'name' => $line->product?->name,
                ] : null,
                'staff' => $line->relationLoaded('staff') ? [
                    'id' => $line->staff?->id,
                    'name' => $line->staff?->name,
                ] : null,
            ])->values()->all()),
            'products' => $this->whenLoaded('products', fn () => $this->products->map(fn ($line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'staff_id' => $line->staff_id,
                'quantity' => (int) $line->quantity,
                'unit_price' => $line->unit_price,
                'line_total' => $line->line_total,
                'sort_order' => $line->sort_order,
                'product' => $line->relationLoaded('product') ? [
                    'id' => $line->product?->id,
                    'name' => $line->product?->name,
                    'sku' => $line->product?->sku,
                    'category' => $line->product?->relationLoaded('category')
                        ? $line->product?->category?->name
                        : null,
                ] : null,
                'staff' => $line->relationLoaded('staff') ? [
                    'id' => $line->staff?->id,
                    'name' => $line->staff?->name,
                ] : null,
            ])->values()->all()),
            'saloon' => $this->whenLoaded('saloon', fn () => [
                'id' => $this->saloon?->id,
                'name' => $this->saloon?->name,
            ]),
        ];
    }
}

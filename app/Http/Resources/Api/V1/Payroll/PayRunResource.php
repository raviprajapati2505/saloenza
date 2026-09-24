<?php

namespace App\Http\Resources\Api\V1\Payroll;

use App\Models\PayRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin PayRun */
class PayRunResource extends JsonResource
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
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'status' => $this->status,
            'total_basic' => $this->total_basic,
            'total_commission' => $this->total_commission,
            'total_gross' => $this->total_gross,
            'notes' => $this->notes,
            'approved_at' => $this->approved_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
            'approver' => $this->whenLoaded('approver', fn () => $this->approver ? [
                'id' => $this->approver->id,
                'name' => $this->approver->name,
            ] : null),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(static fn ($line) => [
                'id' => $line->id,
                'user_id' => $line->user_id,
                'staff_name' => $line->user?->name,
                'staff_email' => $line->user?->email,
                'branch_id' => $line->branch_id,
                'branch_name' => $line->branch?->branch_name,
                'basic_salary' => $line->basic_salary,
                'commission_total' => $line->commission_total,
                'gross' => $line->gross,
                'earnings_json' => $line->earnings_json,
            ])->values()),
        ];
    }
}

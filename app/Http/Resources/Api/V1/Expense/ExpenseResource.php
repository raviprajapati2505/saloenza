<?php

namespace App\Http\Resources\Api\V1\Expense;

use App\Models\Expense;
use App\Support\Expense\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Expense */
class ExpenseResource extends JsonResource
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
            'category' => $this->category,
            'category_label' => ExpenseCategory::label((string) $this->category),
            'title' => $this->title,
            'amount' => $this->amount,
            'incurred_on' => $this->incurred_on?->toDateString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'branch' => $this->whenLoaded('branch', fn () => $this->branch ? [
                'id' => $this->branch->id,
                'branch_name' => $this->branch->branch_name,
            ] : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ] : null),
        ];
    }
}

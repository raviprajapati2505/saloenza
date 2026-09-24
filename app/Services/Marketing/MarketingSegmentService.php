<?php

namespace App\Services\Marketing;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\CustomerSegment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MarketingSegmentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $saloonId, array $data): CustomerSegment
    {
        $type = (string) ($data['type'] ?? CustomerSegment::TYPE_DYNAMIC);
        $rules = $this->normalizeRules($type, $data['rules_json'] ?? $data['rules'] ?? []);

        $segment = CustomerSegment::query()->create([
            'saloon_id' => $saloonId,
            'name' => trim((string) $data['name']),
            'type' => $type,
            'rules_json' => $rules,
            'estimated_size' => 0,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return $this->refreshSize($segment);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(CustomerSegment $segment, array $data): CustomerSegment
    {
        $type = (string) ($data['type'] ?? $segment->type);
        $rules = array_key_exists('rules_json', $data) || array_key_exists('rules', $data)
            ? $this->normalizeRules($type, $data['rules_json'] ?? $data['rules'] ?? [])
            : ($segment->rules_json ?? []);

        $segment->fill([
            'name' => array_key_exists('name', $data) ? trim((string) $data['name']) : $segment->name,
            'type' => $type,
            'rules_json' => $rules,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $segment->is_active,
        ]);
        $segment->save();

        return $this->refreshSize($segment);
    }

    public function refreshSize(CustomerSegment $segment): CustomerSegment
    {
        $customers = $this->resolveCustomers($segment);
        $segment->estimated_size = $customers->count();
        $segment->save();

        return $segment->fresh();
    }

    /**
     * @return Collection<int, Customer>
     */
    public function resolveCustomers(CustomerSegment $segment): Collection
    {
        $base = Customer::query()
            ->forSaloon((int) $segment->saloon_id)
            ->where('is_active', true)
            ->with(['tags', 'appointments' => function ($query) use ($segment): void {
                $query
                    ->where('saloon_id', $segment->saloon_id)
                    ->where('status', 'completed')
                    ->orderByDesc('starts_at');
            }])
            ->get();

        if ($segment->type === CustomerSegment::TYPE_STATIC) {
            $ids = array_map('intval', $segment->rules_json['customer_ids'] ?? []);

            return $base->whereIn('id', $ids)->values();
        }

        return $base->filter(fn (Customer $customer) => $this->applyRules($customer, $segment->rules_json ?? []))->values();
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function applyRules(Customer $customer, array $rules): bool
    {
        $conditions = $rules['conditions'] ?? $rules;
        if (! is_array($conditions) || $conditions === []) {
            return false;
        }

        // Flat list of conditions — all must match (AND).
        if (array_is_list($conditions)) {
            foreach ($conditions as $condition) {
                if (! is_array($condition) || ! $this->matchCondition($customer, $condition)) {
                    return false;
                }
            }

            return true;
        }

        foreach ($conditions as $key => $value) {
            if (! $this->matchCondition($customer, is_array($value) ? array_merge(['field' => $key], $value) : ['field' => $key, 'value' => $value])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private function matchCondition(Customer $customer, array $condition): bool
    {
        $field = (string) ($condition['field'] ?? $condition['type'] ?? '');
        $op = (string) ($condition['op'] ?? $condition['operator'] ?? 'eq');
        $value = $condition['value'] ?? null;

        return match ($field) {
            'tag' => $this->matchTag($customer, $op, $value),
            'last_visit_days' => $this->matchLastVisitDays($customer, $op, $value),
            'spend' => $this->matchNumeric($this->lifetimeSpend($customer), $op, $value),
            'visit_count' => $this->matchNumeric($this->visitCount($customer), $op, $value),
            default => false,
        };
    }

    private function matchTag(Customer $customer, string $op, mixed $value): bool
    {
        $tagNames = $customer->tags->map(fn ($tag) => strtolower((string) $tag->name))->all();
        $needle = strtolower(trim((string) $value));
        $has = in_array($needle, $tagNames, true);

        return match ($op) {
            'has_not', 'not', 'neq' => ! $has,
            default => $has,
        };
    }

    private function matchLastVisitDays(Customer $customer, string $op, mixed $value): bool
    {
        $completed = $customer->appointments
            ->where('status', 'completed')
            ->sortByDesc(fn (Appointment $row) => $row->starts_at?->timestamp ?? 0)
            ->first();

        if ($completed?->starts_at === null) {
            // Never visited — treat as infinitely many days.
            $days = PHP_INT_MAX;
        } else {
            $days = (int) $completed->starts_at->diffInDays(Carbon::now());
        }

        return $this->matchNumeric($days, $op, $value);
    }

    private function lifetimeSpend(Customer $customer): float
    {
        return (float) $customer->appointments
            ->where('status', 'completed')
            ->sum(fn (Appointment $row) => (float) ($row->grand_total ?? 0));
    }

    private function visitCount(Customer $customer): int
    {
        return $customer->appointments->where('status', 'completed')->count();
    }

    private function matchNumeric(float|int $actual, string $op, mixed $value): bool
    {
        $expected = is_array($value) ? $value : (float) $value;

        return match ($op) {
            'gt', '>' => $actual > (float) $expected,
            'gte', '>=' => $actual >= (float) $expected,
            'lt', '<' => $actual < (float) $expected,
            'lte', '<=' => $actual <= (float) $expected,
            'between' => is_array($expected) && count($expected) >= 2
                && $actual >= (float) $expected[0]
                && $actual <= (float) $expected[1],
            'neq', '!=' => $actual != (float) (is_array($expected) ? ($expected[0] ?? 0) : $expected),
            default => $actual == (float) (is_array($expected) ? ($expected[0] ?? 0) : $expected),
        };
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $rules
     * @return array<string, mixed>
     */
    private function normalizeRules(string $type, array $rules): array
    {
        if ($type === CustomerSegment::TYPE_STATIC) {
            $ids = $rules['customer_ids'] ?? $rules;
            if (! is_array($ids)) {
                throw ValidationException::withMessages(['rules_json' => 'Static segments require customer_ids.']);
            }

            return [
                'customer_ids' => array_values(array_unique(array_map('intval', $ids))),
            ];
        }

        if (array_is_list($rules)) {
            return ['conditions' => $rules];
        }

        if (isset($rules['conditions']) && is_array($rules['conditions'])) {
            return ['conditions' => $rules['conditions']];
        }

        return ['conditions' => $rules === [] ? [] : [$rules]];
    }
}

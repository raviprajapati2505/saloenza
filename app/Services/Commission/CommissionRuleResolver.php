<?php

namespace App\Services\Commission;

use App\Models\CommissionRule;
use App\Models\CommissionScheme;
use App\Models\StaffCommissionAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CommissionRuleResolver
{
    /**
     * Resolve commission for a staff line.
     *
     * @param  array{
     *     applies_to?: string,
     *     service_id?: int|null,
     *     product_id?: int|null,
     *     category_id?: int|null,
     *     revenue?: float|int,
     *     duration_minutes?: int|null,
     *     discount_allocated?: float|int,
     *     branch_id?: int|null,
     *     earned_on?: Carbon|string|null,
     *     period_revenue?: float|int|null
     * }  $lineContext
     * @return array{
     *     commission: float,
     *     rate: float,
     *     calc_type: string,
     *     rule_id: int|null,
     *     rule_name: string|null,
     *     basis_amount: float,
     *     scheme_id: int|null
     * }
     */
    public function resolve(User $staff, array $lineContext): array
    {
        $revenue = round((float) ($lineContext['revenue'] ?? 0), 2);
        $discount = round((float) ($lineContext['discount_allocated'] ?? 0), 2);
        $duration = (int) ($lineContext['duration_minutes'] ?? 0);
        $appliesTo = (string) ($lineContext['applies_to'] ?? 'service');
        $earnedOn = $this->toDate($lineContext['earned_on'] ?? null);
        $branchId = isset($lineContext['branch_id']) ? (int) $lineContext['branch_id'] : null;

        if (! Schema::hasTable('staff_commission_assignments')) {
            return $this->fallbackFromStaffRate($staff, $revenue);
        }

        $assignment = $this->findAssignment($staff, $earnedOn, $branchId);

        if ($assignment === null) {
            return $this->fallbackFromStaffRate($staff, $revenue);
        }

        $overridePercent = $assignment->override_percent !== null
            ? (float) $assignment->override_percent
            : null;

        if ($overridePercent !== null) {
            $commission = round($revenue * ($overridePercent / 100), 2);

            return [
                'commission' => $commission,
                'rate' => $overridePercent,
                'calc_type' => 'percent_of_revenue',
                'rule_id' => null,
                'rule_name' => 'Assignment override',
                'basis_amount' => $revenue,
                'scheme_id' => (int) $assignment->scheme_id,
            ];
        }

        $scheme = $assignment->relationLoaded('scheme')
            ? $assignment->scheme
            : CommissionScheme::query()->find($assignment->scheme_id);

        if ($scheme === null || ! $scheme->is_active) {
            return $this->fallbackFromStaffRate($staff, $revenue);
        }

        /** @var Collection<int, CommissionRule> $rules */
        $rules = $scheme->relationLoaded('rules')
            ? $scheme->rules
            : CommissionRule::query()
                ->where('scheme_id', $scheme->id)
                ->where('is_active', true)
                ->orderByDesc('priority')
                ->orderBy('id')
                ->get();

        $rule = $this->matchRule(
            $rules->where('is_active', true)->sortByDesc('priority')->values(),
            $staff,
            $appliesTo,
            isset($lineContext['service_id']) ? (int) $lineContext['service_id'] : null,
            isset($lineContext['product_id']) ? (int) $lineContext['product_id'] : null,
            isset($lineContext['category_id']) ? (int) $lineContext['category_id'] : null,
        );

        if ($rule === null) {
            return $this->fallbackFromStaffRate($staff, $revenue);
        }

        return $this->calculateFromRule(
            $rule,
            $revenue,
            $discount,
            $duration,
            isset($lineContext['period_revenue']) ? (float) $lineContext['period_revenue'] : $revenue,
        );
    }

    /**
     * Seed a default scheme + rules from existing staff flat rates.
     */
    public function migrateFromFlatRates(int $saloonId): ?CommissionScheme
    {
        if (! Schema::hasTable('commission_schemes')) {
            return null;
        }

        $existingDefault = CommissionScheme::query()
            ->where('saloon_id', $saloonId)
            ->where('is_default', true)
            ->first();

        if ($existingDefault !== null) {
            return $existingDefault;
        }

        $staffWithRates = User::query()
            ->staff()
            ->where('saloon_id', $saloonId)
            ->whereNotNull('commission_rate')
            ->where('commission_rate', '>', 0)
            ->get();

        return DB::transaction(function () use ($saloonId, $staffWithRates): CommissionScheme {
            $scheme = CommissionScheme::query()->create([
                'saloon_id' => $saloonId,
                'name' => 'Default (from flat rates)',
                'is_default' => true,
                'is_active' => true,
                'commission_on_no_show' => false,
                'commission_when_payment_unpaid' => 'on_complete',
            ]);

            $rates = $staffWithRates
                ->map(fn (User $user) => round((float) $user->commission_rate, 4))
                ->unique()
                ->values();

            if ($rates->isEmpty()) {
                CommissionRule::query()->create([
                    'scheme_id' => $scheme->id,
                    'priority' => 0,
                    'name' => 'Default percent of revenue',
                    'applies_to' => 'all',
                    'calc_type' => 'percent_of_revenue',
                    'rate_value' => 0,
                    'include_discounts' => true,
                    'is_active' => true,
                ]);
            } elseif ($rates->count() === 1) {
                CommissionRule::query()->create([
                    'scheme_id' => $scheme->id,
                    'priority' => 0,
                    'name' => 'Default percent of revenue',
                    'applies_to' => 'all',
                    'calc_type' => 'percent_of_revenue',
                    'rate_value' => $rates->first(),
                    'include_discounts' => true,
                    'is_active' => true,
                ]);
            } else {
                foreach ($staffWithRates as $index => $user) {
                    CommissionRule::query()->create([
                        'scheme_id' => $scheme->id,
                        'priority' => 100 - $index,
                        'name' => "Rate for {$user->name}",
                        'applies_to' => 'all',
                        'staff_user_id' => $user->id,
                        'calc_type' => 'percent_of_revenue',
                        'rate_value' => (float) $user->commission_rate,
                        'include_discounts' => true,
                        'is_active' => true,
                    ]);
                }
            }

            foreach ($staffWithRates as $user) {
                StaffCommissionAssignment::query()->create([
                    'user_id' => $user->id,
                    'saloon_id' => $saloonId,
                    'branch_id' => $user->branch_id,
                    'scheme_id' => $scheme->id,
                    'effective_from' => now()->toDateString(),
                ]);
            }

            return $scheme->load('rules');
        });
    }

    /**
     * @return array{
     *     commission: float,
     *     rate: float,
     *     calc_type: string,
     *     rule_id: int|null,
     *     rule_name: string|null,
     *     basis_amount: float,
     *     scheme_id: int|null
     * }
     */
    private function fallbackFromStaffRate(User $staff, float $revenue): array
    {
        $rate = (float) ($staff->commission_rate ?? 0);
        $commission = round($revenue * ($rate / 100), 2);

        return [
            'commission' => $commission,
            'rate' => $rate,
            'calc_type' => 'percent_of_revenue',
            'rule_id' => null,
            'rule_name' => null,
            'basis_amount' => $revenue,
            'scheme_id' => null,
        ];
    }

    private function findAssignment(User $staff, ?Carbon $earnedOn, ?int $branchId): ?StaffCommissionAssignment
    {
        $saloonId = $staff->saloon_id !== null ? (int) $staff->saloon_id : null;
        if ($saloonId === null) {
            return null;
        }

        $date = $earnedOn?->toDateString() ?? now()->toDateString();

        $query = StaffCommissionAssignment::query()
            ->with(['scheme.rules' => fn ($q) => $q->where('is_active', true)->orderByDesc('priority')->orderBy('id')])
            ->where('user_id', $staff->id)
            ->where('saloon_id', $saloonId)
            ->where(function ($q) use ($date): void {
                $q->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date);
            })
            ->where(function ($q) use ($date): void {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date);
            })
            ->whereHas('scheme', fn ($q) => $q->where('is_active', true));

        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId): void {
                $q->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
        }

        return $query
            ->orderByRaw('CASE WHEN branch_id IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @param  Collection<int, CommissionRule>  $rules
     */
    private function matchRule(
        Collection $rules,
        User $staff,
        string $appliesTo,
        ?int $serviceId,
        ?int $productId,
        ?int $categoryId,
    ): ?CommissionRule {
        $scored = [];

        foreach ($rules as $rule) {
            if (! $this->ruleMatches($rule, $staff, $appliesTo, $serviceId, $productId, $categoryId)) {
                continue;
            }

            $specificity = 0;
            if ($rule->service_id !== null) {
                $specificity += 40;
            }
            if ($rule->product_id !== null) {
                $specificity += 40;
            }
            if ($rule->category_id !== null) {
                $specificity += 20;
            }
            if ($rule->staff_user_id !== null) {
                $specificity += 10;
            }
            if ($rule->role_id !== null) {
                $specificity += 5;
            }
            if ($rule->applies_to !== 'all') {
                $specificity += 2;
            }

            $scored[] = [
                'rule' => $rule,
                'priority' => (int) $rule->priority,
                'specificity' => $specificity,
            ];
        }

        if ($scored === []) {
            return null;
        }

        usort($scored, static function (array $a, array $b): int {
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] <=> $a['priority'];
            }

            return $b['specificity'] <=> $a['specificity'];
        });

        return $scored[0]['rule'];
    }

    private function ruleMatches(
        CommissionRule $rule,
        User $staff,
        string $appliesTo,
        ?int $serviceId,
        ?int $productId,
        ?int $categoryId,
    ): bool {
        if ($rule->applies_to !== 'all' && $rule->applies_to !== $appliesTo) {
            return false;
        }

        if ($rule->staff_user_id !== null && (int) $rule->staff_user_id !== (int) $staff->id) {
            return false;
        }

        if ($rule->role_id !== null && (int) $rule->role_id !== (int) ($staff->role_id ?? 0)) {
            return false;
        }

        if ($rule->service_id !== null) {
            if ($serviceId === null || (int) $rule->service_id !== $serviceId) {
                return false;
            }
        }

        if ($rule->product_id !== null) {
            if ($productId === null || (int) $rule->product_id !== $productId) {
                return false;
            }
        }

        if ($rule->category_id !== null) {
            if ($categoryId === null || (int) $rule->category_id !== $categoryId) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{
     *     commission: float,
     *     rate: float,
     *     calc_type: string,
     *     rule_id: int|null,
     *     rule_name: string|null,
     *     basis_amount: float,
     *     scheme_id: int|null
     * }
     */
    private function calculateFromRule(
        CommissionRule $rule,
        float $revenue,
        float $discount,
        int $duration,
        float $periodRevenue,
    ): array {
        if ($rule->min_line_price !== null && $revenue < (float) $rule->min_line_price) {
            return [
                'commission' => 0.0,
                'rate' => (float) $rule->rate_value,
                'calc_type' => (string) $rule->calc_type,
                'rule_id' => (int) $rule->id,
                'rule_name' => $rule->name,
                'basis_amount' => $revenue,
                'scheme_id' => (int) $rule->scheme_id,
            ];
        }

        // Line `revenue` is the charged amount. `discount_allocated` is the share of
        // appointment discount. include_discounts=false → restore discount onto basis (pre-discount).
        $includeDiscounts = (bool) $rule->include_discounts;
        $preDiscount = round($revenue + max($discount, 0), 2);
        $grossOrCharged = $includeDiscounts ? $revenue : $preDiscount;
        $percentNetBasis = max($revenue - $discount, 0.0);

        $rate = (float) $rule->rate_value;
        $calcType = (string) $rule->calc_type;
        $commission = 0.0;
        $basis = $grossOrCharged;

        switch ($calcType) {
            case 'percent_of_net':
                $basis = $includeDiscounts ? $percentNetBasis : $grossOrCharged;
                $commission = round($basis * ($rate / 100), 2);
                break;
            case 'fixed_per_line':
                $commission = round($rate, 2);
                $basis = $revenue;
                break;
            case 'fixed_per_minute':
                $commission = round(max($duration, 0) * $rate, 2);
                $basis = (float) max($duration, 0);
                break;
            case 'tiered_percent':
                $rate = $this->tierRateFor($rule->tier_json ?? [], $periodRevenue);
                $basis = $grossOrCharged;
                $commission = round($basis * ($rate / 100), 2);
                break;
            case 'percent_of_revenue':
            default:
                $basis = $grossOrCharged;
                $commission = round($basis * ($rate / 100), 2);
                break;
        }

        if ($revenue <= 0 && in_array($calcType, ['percent_of_revenue', 'percent_of_net', 'tiered_percent'], true)) {
            $commission = 0.0;
        }

        return [
            'commission' => $commission,
            'rate' => $rate,
            'calc_type' => $calcType,
            'rule_id' => (int) $rule->id,
            'rule_name' => $rule->name,
            'basis_amount' => round($basis, 2),
            'scheme_id' => (int) $rule->scheme_id,
        ];
    }

    /**
     * @param  list<array{min?: float|int, max?: float|int|null, rate?: float|int}>|null  $tiers
     */
    private function tierRateFor(?array $tiers, float $periodRevenue): float
    {
        if ($tiers === null || $tiers === []) {
            return 0.0;
        }

        foreach ($tiers as $tier) {
            $min = (float) ($tier['min'] ?? 0);
            $max = array_key_exists('max', $tier) && $tier['max'] !== null
                ? (float) $tier['max']
                : null;

            if ($periodRevenue < $min) {
                continue;
            }

            if ($max !== null && $periodRevenue > $max) {
                continue;
            }

            return (float) ($tier['rate'] ?? 0);
        }

        // Fallback to last tier rate when above all max bounds
        $last = $tiers[array_key_last($tiers)];

        return (float) ($last['rate'] ?? 0);
    }

    private function toDate(Carbon|string|null $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }

        return Carbon::parse($value)->startOfDay();
    }
}

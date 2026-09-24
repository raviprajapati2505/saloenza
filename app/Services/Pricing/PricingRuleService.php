<?php

namespace App\Services\Pricing;

use App\Models\PricingRule;
use App\Models\Service;
use App\Support\Catalog\SalonOfferingResolver;
use App\Support\Pricing\PricingAdjustmentType;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class PricingRuleService
{
    /**
     * Resolve list vs final price for a service at a given slot.
     *
     * @return array{
     *     list_price: float,
     *     final_price: float,
     *     adjustment_amount: float,
     *     pricing_rule: ?PricingRule,
     *     pricing_rule_id: ?int
     * }
     */
    public function resolvePrice(
        int $saloonId,
        ?int $branchId,
        int $serviceId,
        CarbonInterface|string|null $datetime = null,
        string $channel = 'all',
        ?int $staffId = null,
        ?float $listPriceOverride = null,
    ): array {
        $slot = $datetime instanceof CarbonInterface
            ? Carbon::instance($datetime)
            : ($datetime ? Carbon::parse($datetime) : now());

        $listPrice = $listPriceOverride;
        if ($listPrice === null) {
            $offering = SalonOfferingResolver::find($saloonId, $serviceId, null, $branchId);
            $listPrice = $offering !== null
                ? (float) $offering->price
                : (float) (Service::query()->whereKey($serviceId)->value('default_price') ?? 0);
        }

        $listPrice = round(max(0, (float) $listPrice), 2);
        $rule = $this->matchingRule($saloonId, $branchId, $serviceId, $slot, $channel, $staffId);

        if ($rule === null) {
            return [
                'list_price' => $listPrice,
                'final_price' => $listPrice,
                'adjustment_amount' => 0.0,
                'pricing_rule' => null,
                'pricing_rule_id' => null,
            ];
        }

        $final = $this->applyAdjustment($listPrice, $rule);
        $final = round(max(0, $final), 2);

        return [
            'list_price' => $listPrice,
            'final_price' => $final,
            'adjustment_amount' => round($final - $listPrice, 2),
            'pricing_rule' => $rule,
            'pricing_rule_id' => (int) $rule->id,
        ];
    }

    public function matchingRule(
        int $saloonId,
        ?int $branchId,
        int $serviceId,
        CarbonInterface $slot,
        string $channel = 'all',
        ?int $staffId = null,
    ): ?PricingRule {
        $categoryId = Service::query()->whereKey($serviceId)->value('category_id');
        $categoryId = $categoryId !== null ? (int) $categoryId : null;
        $dayOfWeek = (int) $slot->dayOfWeek;
        $time = $slot->format('H:i:s');
        $date = $slot->toDateString();
        $leadHours = max(0, now()->diffInMinutes($slot, false) / 60);

        $rules = PricingRule::query()
            ->where('saloon_id', $saloonId)
            ->where('is_active', true)
            ->where(function ($query) use ($branchId): void {
                $query->whereNull('branch_id');
                if ($branchId !== null) {
                    $query->orWhere('branch_id', $branchId);
                }
            })
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        foreach ($rules as $rule) {
            if (! $this->channelMatches((string) $rule->channel, $channel)) {
                continue;
            }

            $serviceIds = array_map('intval', $rule->service_ids ?? []);
            if ($serviceIds !== [] && ! in_array($serviceId, $serviceIds, true)) {
                continue;
            }

            $categoryIds = array_map('intval', $rule->category_ids ?? []);
            if ($categoryIds !== [] && ($categoryId === null || ! in_array($categoryId, $categoryIds, true))) {
                continue;
            }

            $staffIds = array_map('intval', $rule->staff_ids ?? []);
            if ($staffIds !== [] && ($staffId === null || ! in_array($staffId, $staffIds, true))) {
                continue;
            }

            $days = array_map('intval', $rule->days_of_week ?? []);
            if ($days !== [] && ! in_array($dayOfWeek, $days, true)) {
                continue;
            }

            if ($rule->time_start && $rule->time_end) {
                $start = (string) $rule->time_start;
                $end = (string) $rule->time_end;
                if ($start <= $end) {
                    if ($time < $start || $time > $end) {
                        continue;
                    }
                } elseif ($time < $start && $time > $end) {
                    continue;
                }
            }

            if ($rule->date_from && $date < $rule->date_from->toDateString()) {
                continue;
            }
            if ($rule->date_to && $date > $rule->date_to->toDateString()) {
                continue;
            }

            if ($rule->min_lead_hours !== null && $leadHours < (float) $rule->min_lead_hours) {
                continue;
            }

            return $rule;
        }

        return null;
    }

    private function applyAdjustment(float $listPrice, PricingRule $rule): float
    {
        $value = (float) $rule->adjustment_value;

        return match ((string) $rule->adjustment_type) {
            PricingAdjustmentType::PERCENT_OFF => $listPrice * (1 - ($value / 100)),
            PricingAdjustmentType::PERCENT_ON => $listPrice * (1 + ($value / 100)),
            PricingAdjustmentType::FIXED_PRICE => $value,
            PricingAdjustmentType::FIXED_OFF => $listPrice - $value,
            default => $listPrice,
        };
    }

    private function channelMatches(string $ruleChannel, string $requestChannel): bool
    {
        if ($ruleChannel === 'all' || $requestChannel === 'all') {
            return true;
        }

        $normalized = match ($requestChannel) {
            'self_booking', 'public', 'online' => 'self_booking',
            'pos', 'walk_in', 'internal' => 'internal',
            default => $requestChannel,
        };

        return $ruleChannel === $normalized || $ruleChannel === $requestChannel;
    }
}

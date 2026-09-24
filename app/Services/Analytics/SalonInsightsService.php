<?php

namespace App\Services\Analytics;

use App\Models\Appointment;
use App\Models\CustomerValueStat;
use App\Models\SalonInsightSetting;
use Carbon\Carbon;

class SalonInsightsService
{
    /**
     * @return array{
     *     as_of: string,
     *     saloon_id: int|null,
     *     branch_id: int|null,
     *     settings: array<string, mixed>,
     *     cards: list<array<string, mixed>>
     * }
     */
    public function build(?int $saloonId, ?int $branchId = null): array
    {
        $settings = $this->resolveSettings($saloonId);
        $lookbackDays = (int) $settings['lookback_days'];
        $to = now()->endOfDay();
        $from = now()->subDays($lookbackDays)->startOfDay();

        $cards = [
            $this->overdueCustomersCard($saloonId, $branchId, $settings),
            $this->dayOfWeekImbalanceCard($saloonId, $branchId, $from, $to, $settings),
            $this->categoryTrendsCard($saloonId, $branchId),
            $this->noShowLossCard($saloonId, $branchId, $from, $to),
            $this->recoveryEstimateCard($saloonId, $branchId, $settings),
        ];

        $enabled = $settings['enabled_insight_types'];
        if (is_array($enabled) && $enabled !== []) {
            $cards = array_values(array_filter(
                $cards,
                fn (array $card): bool => in_array($card['type'], $enabled, true),
            ));
        }

        return [
            'as_of' => now()->toISOString(),
            'saloon_id' => $saloonId,
            'branch_id' => $branchId,
            'settings' => $settings,
            'cards' => $cards,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSettings(?int $saloonId): array
    {
        $defaults = [
            'lapse_days_threshold' => 45,
            'high_value_spend_threshold' => null,
            'imbalance_variance_pct' => 25,
            'lookback_days' => 90,
            'recovery_rate_assumption' => 0.15,
            'enabled_insight_types' => null,
        ];

        if ($saloonId === null) {
            return $defaults;
        }

        $row = SalonInsightSetting::query()->where('saloon_id', $saloonId)->first();
        if (! $row) {
            return $defaults;
        }

        return [
            'lapse_days_threshold' => (int) $row->lapse_days_threshold,
            'high_value_spend_threshold' => $row->high_value_spend_threshold !== null
                ? (float) $row->high_value_spend_threshold
                : null,
            'imbalance_variance_pct' => (int) $row->imbalance_variance_pct,
            'lookback_days' => (int) $row->lookback_days,
            'recovery_rate_assumption' => (float) $row->recovery_rate_assumption,
            'enabled_insight_types' => $row->enabled_insight_types,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function overdueCustomersCard(?int $saloonId, ?int $branchId, array $settings): array
    {
        if ($saloonId === null) {
            return $this->emptyCard('overdue_customers', 'Customers about to lapse', 'info');
        }

        $threshold = (int) $settings['lapse_days_threshold'];
        $cutoff = now()->subDays($threshold);

        $query = CustomerValueStat::query()
            ->where('saloon_id', $saloonId)
            ->where('visit_count', '>=', 1)
            ->where(function ($q) use ($cutoff): void {
                $q->whereIn('lapse_status', ['at_risk', 'lapsed', 'lost'])
                    ->orWhere(function ($inner) use ($cutoff): void {
                        $inner->whereNotNull('last_visit_at')
                            ->where('last_visit_at', '<', $cutoff);
                    });
            })
            ->with(['customer:id,name,email,phone,is_active'])
            ->orderByDesc('lifetime_spend');

        $stats = $query->limit(50)->get();

        if ($branchId !== null) {
            $customerIds = Appointment::query()
                ->where('saloon_id', $saloonId)
                ->where('branch_id', $branchId)
                ->whereNotNull('customer_id')
                ->distinct()
                ->pluck('customer_id')
                ->all();
            $allowed = array_fill_keys(array_map('intval', $customerIds), true);
            $stats = $stats->filter(fn (CustomerValueStat $s) => isset($allowed[(int) $s->customer_id]))->values();
        }

        $avgTicket = $stats->count() > 0 ? (float) $stats->avg('avg_ticket') : 0.0;
        $recoveryRate = (float) $settings['recovery_rate_assumption'];
        $recoverable = round($avgTicket * $recoveryRate * $stats->count(), 2);

        $drilldown = $stats->take(25)->map(function (CustomerValueStat $stat): array {
            return [
                'customer_id' => (int) $stat->customer_id,
                'name' => $stat->customer?->name,
                'lapse_status' => (string) $stat->lapse_status,
                'clv_tier' => (string) $stat->clv_tier,
                'lifetime_spend' => (float) $stat->lifetime_spend,
                'last_visit_at' => $stat->last_visit_at?->toISOString(),
                'days_since_visit' => $stat->last_visit_at
                    ? (int) $stat->last_visit_at->diffInDays(now())
                    : null,
            ];
        })->all();

        $severity = $stats->count() >= 20 ? 'critical' : ($stats->count() >= 5 ? 'warn' : 'info');

        return [
            'type' => 'overdue_customers',
            'title' => 'Customers about to lapse',
            'summary' => $stats->count() === 0
                ? 'No overdue customers in the current window.'
                : sprintf('%d customers have not visited in %d+ days.', $stats->count(), $threshold),
            'severity' => $severity,
            'metrics' => [
                'count' => $stats->count(),
                'lapse_days_threshold' => $threshold,
                'estimated_recoverable_revenue' => $recoverable,
                'avg_ticket' => round($avgTicket, 2),
            ],
            'recommended_action_code' => 'open_winback_list',
            'drilldown' => $drilldown,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function dayOfWeekImbalanceCard(
        ?int $saloonId,
        ?int $branchId,
        Carbon $from,
        Carbon $to,
        array $settings,
    ): array {
        $query = $this->completedQuery($saloonId, $branchId, $from, $to);
        $rows = $query->get(['starts_at', 'grand_total', 'amount_paid', 'price']);

        $byDow = array_fill(0, 7, ['revenue' => 0.0, 'count' => 0]);
        foreach ($rows as $row) {
            if (! $row->starts_at) {
                continue;
            }
            $dow = (int) $row->starts_at->dayOfWeek; // 0=Sun
            $amount = (float) ($row->amount_paid ?: $row->grand_total ?: $row->price ?: 0);
            $byDow[$dow]['revenue'] += $amount;
            $byDow[$dow]['count']++;
        }

        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $series = [];
        $revenues = [];
        foreach ($byDow as $dow => $data) {
            $series[] = [
                'day' => $labels[$dow],
                'dow' => $dow,
                'revenue' => round($data['revenue'], 2),
                'count' => $data['count'],
            ];
            $revenues[] = $data['revenue'];
        }

        $mean = count($revenues) > 0 ? array_sum($revenues) / 7 : 0.0;
        $variancePct = max(1, (int) $settings['imbalance_variance_pct']) / 100;
        $soft = [];
        $peak = [];
        foreach ($series as $item) {
            if ($mean <= 0) {
                continue;
            }
            $delta = ($item['revenue'] - $mean) / $mean;
            if ($delta <= -$variancePct) {
                $soft[] = $item['day'];
            } elseif ($delta >= $variancePct) {
                $peak[] = $item['day'];
            }
        }

        $severity = (count($soft) + count($peak)) >= 3 ? 'warn' : 'info';

        return [
            'type' => 'day_of_week_imbalance',
            'title' => 'Day-of-week imbalance',
            'summary' => $mean <= 0
                ? 'Not enough completed revenue to detect imbalance.'
                : sprintf(
                    'Soft days: %s. Peak days: %s.',
                    $soft === [] ? 'none' : implode(', ', $soft),
                    $peak === [] ? 'none' : implode(', ', $peak),
                ),
            'severity' => $severity,
            'metrics' => [
                'mean_daily_revenue' => round($mean, 2),
                'imbalance_variance_pct' => (int) $settings['imbalance_variance_pct'],
                'soft_days' => $soft,
                'peak_days' => $peak,
                'by_day' => $series,
            ],
            'recommended_action_code' => 'promo_offpeak',
            'drilldown' => $series,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryTrendsCard(?int $saloonId, ?int $branchId): array
    {
        $currentFrom = now()->subDays(30)->startOfDay();
        $currentTo = now()->endOfDay();
        $priorFrom = now()->subDays(60)->startOfDay();
        $priorTo = now()->subDays(30)->endOfDay();

        $current = $this->categoryRevenueMap($saloonId, $branchId, $currentFrom, $currentTo);
        $prior = $this->categoryRevenueMap($saloonId, $branchId, $priorFrom, $priorTo);

        $names = array_unique(array_merge(array_keys($current), array_keys($prior)));
        $trends = [];
        foreach ($names as $name) {
            $curr = $current[$name] ?? 0.0;
            $prev = $prior[$name] ?? 0.0;
            $delta = $curr - $prev;
            $pct = $prev > 0 ? round(($delta / $prev) * 100, 1) : ($curr > 0 ? 100.0 : 0.0);
            $trends[] = [
                'category' => $name,
                'current_revenue' => round($curr, 2),
                'prior_revenue' => round($prev, 2),
                'delta' => round($delta, 2),
                'delta_pct' => $pct,
            ];
        }

        usort($trends, fn ($a, $b) => abs($b['delta_pct']) <=> abs($a['delta_pct']));
        $top = array_slice($trends, 0, 10);
        $movers = array_slice(array_filter($top, fn ($t) => abs($t['delta_pct']) >= 15), 0, 5);

        return [
            'type' => 'category_trends',
            'title' => 'Category revenue trends',
            'summary' => $top === []
                ? 'No category revenue in the last 60 days.'
                : sprintf('%d categories moved ±15%% vs prior 30 days.', count($movers)),
            'severity' => count($movers) >= 3 ? 'warn' : 'info',
            'metrics' => [
                'window_days' => 30,
                'movers_count' => count($movers),
            ],
            'recommended_action_code' => 'review_category_mix',
            'drilldown' => $top,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function noShowLossCard(?int $saloonId, ?int $branchId, Carbon $from, Carbon $to): array
    {
        $query = Appointment::query()
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('starts_at', [$from, $to])
            ->where('status', 'no-show')
            ->with(['customer:id,name', 'service:id,name']);

        $rows = $query->orderByDesc('starts_at')->limit(100)->get();
        $lost = round((float) $rows->sum(fn ($a) => (float) ($a->grand_total ?? $a->price ?? 0)), 2);

        $drilldown = $rows->take(25)->map(fn ($a) => [
            'id' => (int) $a->id,
            'customer' => $a->customer?->name,
            'service' => $a->service?->name,
            'starts_at' => $a->starts_at?->toISOString(),
            'amount' => (float) ($a->grand_total ?? $a->price ?? 0),
        ])->all();

        return [
            'type' => 'no_show_loss',
            'title' => 'No-show revenue loss',
            'summary' => $rows->isEmpty()
                ? 'No no-shows in the lookback window.'
                : sprintf('%d no-shows left ~%s uncollected.', $rows->count(), number_format($lost, 2)),
            'severity' => $rows->count() >= 10 ? 'critical' : ($rows->count() >= 3 ? 'warn' : 'info'),
            'metrics' => [
                'no_show_count' => $rows->count(),
                'lost_revenue' => $lost,
            ],
            'recommended_action_code' => 'tighten_reminders',
            'drilldown' => $drilldown,
        ];
    }

    /**
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function recoveryEstimateCard(?int $saloonId, ?int $branchId, array $settings): array
    {
        $overdue = $this->overdueCustomersCard($saloonId, $branchId, $settings);
        $count = (int) ($overdue['metrics']['count'] ?? 0);
        $avgTicket = (float) ($overdue['metrics']['avg_ticket'] ?? 0);
        $rate = (float) $settings['recovery_rate_assumption'];
        $estimate = round($count * $avgTicket * $rate, 2);

        return [
            'type' => 'recovery_estimate',
            'title' => 'Win-back recovery estimate',
            'summary' => $count === 0
                ? 'No recoverable revenue estimated right now.'
                : sprintf(
                    'If ~%s%% of %d overdue clients return at avg ticket, recovery ≈ %s.',
                    number_format($rate * 100, 0),
                    $count,
                    number_format($estimate, 2),
                ),
            'severity' => $estimate >= 10000 ? 'warn' : 'info',
            'metrics' => [
                'overdue_count' => $count,
                'avg_ticket' => $avgTicket,
                'recovery_rate_assumption' => $rate,
                'estimated_recovery' => $estimate,
            ],
            'recommended_action_code' => 'run_winback',
            'drilldown' => $overdue['drilldown'] ?? [],
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Appointment>
     */
    private function completedQuery(?int $saloonId, ?int $branchId, Carbon $from, Carbon $to)
    {
        return Appointment::query()
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('starts_at', [$from, $to])
            ->where('status', 'completed');
    }

    /**
     * @return array<string, float>
     */
    private function categoryRevenueMap(?int $saloonId, ?int $branchId, Carbon $from, Carbon $to): array
    {
        $appointments = $this->completedQuery($saloonId, $branchId, $from, $to)
            ->with(['services.service.category:id,name', 'service.category:id,name'])
            ->get();

        $map = [];
        foreach ($appointments as $appointment) {
            $amount = (float) ($appointment->amount_paid ?: $appointment->grand_total ?: $appointment->price ?: 0);
            if ($appointment->services->isNotEmpty()) {
                $lineTotal = (float) $appointment->services->sum('price');
                foreach ($appointment->services as $line) {
                    $name = $line->service?->category?->name ?? 'Uncategorized';
                    $share = $lineTotal > 0
                        ? ($amount * ((float) $line->price / $lineTotal))
                        : ($amount / max($appointment->services->count(), 1));
                    $map[$name] = ($map[$name] ?? 0) + $share;
                }
            } else {
                $name = $appointment->service?->category?->name ?? 'Uncategorized';
                $map[$name] = ($map[$name] ?? 0) + $amount;
            }
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyCard(string $type, string $title, string $severity): array
    {
        return [
            'type' => $type,
            'title' => $title,
            'summary' => 'Select a salon context to compute this insight.',
            'severity' => $severity,
            'metrics' => [],
            'recommended_action_code' => null,
            'drilldown' => [],
        ];
    }
}

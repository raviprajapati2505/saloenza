<?php

namespace App\Services\Customer;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\CustomerValueStat;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CustomerClvService
{
    private const WEIGHT_MONETARY = 0.40;

    private const WEIGHT_FREQUENCY = 0.30;

    private const WEIGHT_RECENCY = 0.30;

    /**
     * @return array{recomputed: int, tiers: array<string, int>}
     */
    public function recomputeForSalon(int $saloonId): array
    {
        $customerIds = Customer::query()
            ->forSaloon($saloonId)
            ->pluck('id');

        $metrics = $this->aggregateMetrics($saloonId, $customerIds->all());
        $scoreContext = $this->buildScoreContext($metrics);

        $recomputed = 0;
        $tiers = ['platinum' => 0, 'gold' => 0, 'silver' => 0, 'bronze' => 0];
        $now = now();

        foreach ($customerIds as $customerId) {
            $row = $metrics[(int) $customerId] ?? $this->emptyMetrics();
            $scored = $this->scoreRow($row, $scoreContext);

            CustomerValueStat::query()->updateOrCreate(
                [
                    'customer_id' => (int) $customerId,
                    'saloon_id' => $saloonId,
                ],
                [
                    'lifetime_spend' => $row['lifetime_spend'],
                    'visit_count' => $row['visit_count'],
                    'avg_ticket' => $row['avg_ticket'],
                    'first_visit_at' => $row['first_visit_at'],
                    'last_visit_at' => $row['last_visit_at'],
                    'avg_days_between_visits' => $row['avg_days_between_visits'],
                    'expected_annual_value' => $row['expected_annual_value'],
                    'clv_score' => $scored['clv_score'],
                    'clv_tier' => $scored['clv_tier'],
                    'churn_risk_score' => $scored['churn_risk_score'],
                    'computed_at' => $now,
                ],
            );

            $tiers[$scored['clv_tier']] = ($tiers[$scored['clv_tier']] ?? 0) + 1;
            $recomputed++;
        }

        return [
            'recomputed' => $recomputed,
            'tiers' => $tiers,
        ];
    }

    public function recomputeCustomer(int $customerId, int $saloonId): CustomerValueStat
    {
        $metrics = $this->aggregateMetrics($saloonId, [$customerId]);
        $allMetrics = $this->aggregateMetrics(
            $saloonId,
            Customer::query()->forSaloon($saloonId)->pluck('id')->all(),
        );
        $scoreContext = $this->buildScoreContext($allMetrics);
        $row = $metrics[$customerId] ?? $this->emptyMetrics();
        $scored = $this->scoreRow($row, $scoreContext);

        return CustomerValueStat::query()->updateOrCreate(
            [
                'customer_id' => $customerId,
                'saloon_id' => $saloonId,
            ],
            [
                'lifetime_spend' => $row['lifetime_spend'],
                'visit_count' => $row['visit_count'],
                'avg_ticket' => $row['avg_ticket'],
                'first_visit_at' => $row['first_visit_at'],
                'last_visit_at' => $row['last_visit_at'],
                'avg_days_between_visits' => $row['avg_days_between_visits'],
                'expected_annual_value' => $row['expected_annual_value'],
                'clv_score' => $scored['clv_score'],
                'clv_tier' => $scored['clv_tier'],
                'churn_risk_score' => $scored['churn_risk_score'],
                'computed_at' => now(),
            ],
        );
    }

    /**
     * @return array{
     *     customers_scored: int,
     *     tiers: array<string, int>,
     *     avg_clv_score: float,
     *     total_lifetime_spend: float,
     *     top_customers: list<array<string, mixed>>
     * }
     */
    public function summary(int $saloonId, int $limit = 10): array
    {
        $stats = CustomerValueStat::query()
            ->where('saloon_id', $saloonId)
            ->with(['customer:id,name,email,phone,is_active'])
            ->get();

        $tiers = ['platinum' => 0, 'gold' => 0, 'silver' => 0, 'bronze' => 0];
        foreach ($stats as $stat) {
            $tier = (string) $stat->clv_tier;
            if (isset($tiers[$tier])) {
                $tiers[$tier]++;
            }
        }

        $top = $stats
            ->sortByDesc('clv_score')
            ->take($limit)
            ->values()
            ->map(fn (CustomerValueStat $stat) => $this->statPayload($stat))
            ->all();

        return [
            'customers_scored' => $stats->count(),
            'tiers' => $tiers,
            'avg_clv_score' => $stats->count() > 0
                ? round((float) $stats->avg('clv_score'), 1)
                : 0.0,
            'total_lifetime_spend' => round((float) $stats->sum('lifetime_spend'), 2),
            'top_customers' => $top,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForSalon(int $saloonId, ?string $tier = null, int $limit = 100): array
    {
        $query = CustomerValueStat::query()
            ->where('saloon_id', $saloonId)
            ->with(['customer:id,name,email,phone,is_active'])
            ->orderByDesc('clv_score');

        if ($tier !== null && $tier !== '') {
            $query->where('clv_tier', $tier);
        }

        return $query->limit($limit)->get()->map(fn (CustomerValueStat $stat) => $this->statPayload($stat))->all();
    }

    /**
     * @param list<int> $customerIds
     * @return array<int, array<string, mixed>>
     */
    private function aggregateMetrics(int $saloonId, array $customerIds): array
    {
        if ($customerIds === []) {
            return [];
        }

        $visits = Appointment::query()
            ->where('saloon_id', $saloonId)
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->whereNotNull('customer_id')
            ->orderBy('starts_at')
            ->get(['customer_id', 'starts_at', 'grand_total', 'amount_paid', 'price']);

        /** @var array<int, Collection<int, Appointment>> $byCustomer */
        $byCustomer = $visits->groupBy('customer_id');
        $result = [];

        foreach ($customerIds as $customerId) {
            $rows = $byCustomer->get($customerId, collect());
            if ($rows->isEmpty()) {
                $result[(int) $customerId] = $this->emptyMetrics();

                continue;
            }

            $spend = round((float) $rows->sum(function (Appointment $appt): float {
                $paid = (float) ($appt->amount_paid ?? 0);
                if ($paid > 0) {
                    return $paid;
                }

                return (float) ($appt->grand_total ?? $appt->price ?? 0);
            }), 2);

            $visitCount = $rows->count();
            $avgTicket = $visitCount > 0 ? round($spend / $visitCount, 2) : 0.0;
            $first = $rows->first()?->starts_at;
            $last = $rows->last()?->starts_at;

            $gaps = [];
            $previous = null;
            foreach ($rows as $row) {
                if ($previous !== null && $row->starts_at !== null) {
                    $gaps[] = $previous->diffInDays($row->starts_at);
                }
                $previous = $row->starts_at;
            }

            $avgDays = count($gaps) > 0 ? round(array_sum($gaps) / count($gaps), 2) : null;
            $expectedAnnual = null;
            if ($visitCount >= 2 && $avgDays !== null && $avgDays > 0) {
                $expectedAnnual = round($avgTicket * (365 / max($avgDays, 1)), 2);
            }

            $result[(int) $customerId] = [
                'lifetime_spend' => $spend,
                'visit_count' => $visitCount,
                'avg_ticket' => $avgTicket,
                'first_visit_at' => $first,
                'last_visit_at' => $last,
                'avg_days_between_visits' => $avgDays,
                'expected_annual_value' => $expectedAnnual,
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array<string, mixed>> $metrics
     * @return array{max_spend: float, max_visits: int}
     */
    private function buildScoreContext(array $metrics): array
    {
        $maxSpend = 0.0;
        $maxVisits = 0;

        foreach ($metrics as $row) {
            $maxSpend = max($maxSpend, (float) $row['lifetime_spend']);
            $maxVisits = max($maxVisits, (int) $row['visit_count']);
        }

        return [
            'max_spend' => max($maxSpend, 1.0),
            'max_visits' => max($maxVisits, 1),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @param array{max_spend: float, max_visits: int} $context
     * @return array{clv_score: int, clv_tier: string, churn_risk_score: int|null}
     */
    private function scoreRow(array $row, array $context): array
    {
        if ((int) $row['visit_count'] === 0) {
            return [
                'clv_score' => 0,
                'clv_tier' => 'bronze',
                'churn_risk_score' => null,
            ];
        }

        $monetary = min(100, ((float) $row['lifetime_spend'] / $context['max_spend']) * 100);
        $frequency = min(100, ((int) $row['visit_count'] / $context['max_visits']) * 100);

        $daysSince = $row['last_visit_at'] instanceof Carbon
            ? max(0, $row['last_visit_at']->diffInDays(now()))
            : 365;
        $recency = max(0, 100 - min(100, ($daysSince / 180) * 100));

        $score = (int) round(
            (self::WEIGHT_MONETARY * $monetary)
            + (self::WEIGHT_FREQUENCY * $frequency)
            + (self::WEIGHT_RECENCY * $recency),
        );
        $score = max(0, min(100, $score));

        $tier = match (true) {
            $score >= 80 => 'platinum',
            $score >= 60 => 'gold',
            $score >= 40 => 'silver',
            default => 'bronze',
        };

        $churnRisk = null;
        $avgDays = $row['avg_days_between_visits'];
        if ($avgDays !== null && (float) $avgDays > 0) {
            $ratio = $daysSince / max((float) $avgDays, 1);
            $churnRisk = (int) max(0, min(100, round(($ratio - 1) * 50)));
        } elseif ($daysSince > 60) {
            $churnRisk = (int) min(100, round(($daysSince / 180) * 100));
        }

        return [
            'clv_score' => $score,
            'clv_tier' => $tier,
            'churn_risk_score' => $churnRisk,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMetrics(): array
    {
        return [
            'lifetime_spend' => 0.0,
            'visit_count' => 0,
            'avg_ticket' => 0.0,
            'first_visit_at' => null,
            'last_visit_at' => null,
            'avg_days_between_visits' => null,
            'expected_annual_value' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statPayload(CustomerValueStat $stat): array
    {
        return [
            'customer_id' => (int) $stat->customer_id,
            'customer' => $stat->customer ? [
                'id' => (int) $stat->customer->id,
                'name' => $stat->customer->name,
                'email' => $stat->customer->email,
                'phone' => $stat->customer->phone,
                'is_active' => (bool) $stat->customer->is_active,
            ] : null,
            'lifetime_spend' => (float) $stat->lifetime_spend,
            'visit_count' => (int) $stat->visit_count,
            'avg_ticket' => (float) $stat->avg_ticket,
            'first_visit_at' => $stat->first_visit_at?->toISOString(),
            'last_visit_at' => $stat->last_visit_at?->toISOString(),
            'avg_days_between_visits' => $stat->avg_days_between_visits !== null
                ? (float) $stat->avg_days_between_visits
                : null,
            'expected_annual_value' => $stat->expected_annual_value !== null
                ? (float) $stat->expected_annual_value
                : null,
            'clv_score' => (int) $stat->clv_score,
            'clv_tier' => (string) $stat->clv_tier,
            'churn_risk_score' => $stat->churn_risk_score !== null ? (int) $stat->churn_risk_score : null,
            'lapse_status' => (string) $stat->lapse_status,
            'computed_at' => $stat->computed_at?->toISOString(),
        ];
    }
}

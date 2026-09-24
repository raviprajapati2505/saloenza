<?php

namespace App\Services\Analytics;

use App\Models\Appointment;
use App\Models\Expense;
use App\Models\SaloonBranch;
use Illuminate\Support\Collection;

class BranchBenchmarkService
{
    /**
     * On-the-fly branch × KPI matrix for a date range, including salon overhead.
     *
     * @return array{range: array{from: string, to: string}, rows: list<array<string, mixed>>}
     */
    public function compare(int $saloonId, string $from, string $to): array
    {
        $branches = SaloonBranch::query()
            ->where('saloon_id', $saloonId)
            ->where('is_active', true)
            ->orderBy('branch_name')
            ->get(['id', 'branch_name']);

        $appointments = Appointment::query()
            ->where('saloon_id', $saloonId)
            ->whereDate('starts_at', '>=', $from)
            ->whereDate('starts_at', '<=', $to)
            ->get(['id', 'branch_id', 'status', 'amount_paid', 'grand_total']);

        $expenses = Expense::query()
            ->where('saloon_id', $saloonId)
            ->incurredBetween($from, $to)
            ->get(['id', 'branch_id', 'amount']);

        $rows = [];

        foreach ($branches as $branch) {
            $branchId = (int) $branch->id;
            $branchAppts = $appointments->where('branch_id', $branchId);
            $branchExpenses = $expenses->where('branch_id', $branchId);

            $rows[] = $this->buildRow(
                $branchId,
                (string) $branch->branch_name,
                false,
                $branchAppts,
                $branchExpenses,
            );
        }

        $overheadExpenses = $expenses->filter(fn (Expense $row) => $row->branch_id === null);
        $rows[] = $this->buildRow(
            null,
            'Salon overhead',
            true,
            collect(),
            $overheadExpenses,
        );

        $this->attachRanks($rows);

        return [
            'range' => ['from' => $from, 'to' => $to],
            'rows' => $rows,
        ];
    }

    /**
     * @param  Collection<int, Appointment>  $appointments
     * @param  Collection<int, Expense>  $expenses
     * @return array<string, mixed>
     */
    private function buildRow(
        ?int $branchId,
        string $label,
        bool $isOverhead,
        Collection $appointments,
        Collection $expenses,
    ): array {
        $completed = $appointments->where('status', 'completed');
        $noShows = $appointments->where('status', 'no-show');
        $revenue = round((float) $completed->sum(fn (Appointment $row) => (float) ($row->amount_paid ?? 0)), 2);
        $completedCount = $completed->count();
        $noShowCount = $noShows->count();
        $expenseTotal = round((float) $expenses->sum(fn (Expense $row) => (float) $row->amount), 2);

        return [
            'branch_id' => $branchId,
            'label' => $label,
            'is_overhead' => $isOverhead,
            'revenue' => $revenue,
            'completed' => $completedCount,
            'no_shows' => $noShowCount,
            'avg_ticket' => $completedCount > 0 ? round($revenue / $completedCount, 2) : 0.0,
            'expenses' => $expenseTotal,
            'ranks' => [],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function attachRanks(array &$rows): void
    {
        $metrics = ['revenue', 'completed', 'no_shows', 'avg_ticket', 'expenses'];

        foreach ($metrics as $metric) {
            $ranked = $rows;
            $lowerIsBetter = in_array($metric, ['no_shows', 'expenses'], true);
            usort($ranked, function (array $a, array $b) use ($metric, $lowerIsBetter): int {
                // Overhead row ranks last for appointment KPIs; still rank expenses normally.
                if ($metric !== 'expenses') {
                    if (($a['is_overhead'] ?? false) !== ($b['is_overhead'] ?? false)) {
                        return ($a['is_overhead'] ?? false) ? 1 : -1;
                    }
                }

                return $lowerIsBetter
                    ? (($a[$metric] ?? 0) <=> ($b[$metric] ?? 0))
                    : (($b[$metric] ?? 0) <=> ($a[$metric] ?? 0));
            });

            $rankMap = [];
            $rank = 1;
            foreach ($ranked as $row) {
                $key = $row['branch_id'] === null ? 'overhead' : (string) $row['branch_id'];
                $rankMap[$key] = $rank;
                $rank++;
            }

            foreach ($rows as &$row) {
                $key = $row['branch_id'] === null ? 'overhead' : (string) $row['branch_id'];
                $row['ranks'][$metric] = $rankMap[$key] ?? null;
            }
            unset($row);
        }
    }
}

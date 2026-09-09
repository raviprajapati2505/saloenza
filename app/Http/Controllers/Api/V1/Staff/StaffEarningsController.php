<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Staff\StaffEarningsService;
use App\Support\Staff\StaffEarningsAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffEarningsController extends Controller
{
    public function __construct(
        private readonly StaffEarningsService $earnings,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'staff_id' => ['sometimes', 'integer', 'exists:users,id'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'preset' => ['sometimes', 'string', Rule::in(['today', 'current_month', 'previous_month'])],
            'search' => ['sometimes', 'string', 'max:120'],
        ]);

        $staff = StaffEarningsAccess::resolveTargetStaff(
            $user,
            isset($validated['staff_id']) ? (int) $validated['staff_id'] : null,
        );

        if (isset($validated['preset'])) {
            $presets = $this->earnings->presets($staff, $user);
            $report = $presets[$validated['preset']];

            if (! empty($validated['search'])) {
                $report = $this->filterReport($report, $validated['search']);
            }

            return response()->json([
                'message' => 'Staff earnings fetched successfully.',
                'data' => [
                    'preset' => $validated['preset'],
                    'report' => $report,
                ],
            ]);
        }

        $from = isset($validated['from'])
            ? now()->parse($validated['from'])->startOfDay()
            : now()->startOfDay();
        $to = isset($validated['to'])
            ? now()->parse($validated['to'])->endOfDay()
            : now()->endOfDay();

        $report = $this->earnings->report($staff, $from, $to, $user);

        if (! empty($validated['search'])) {
            $report = $this->filterReport($report, $validated['search']);
        }

        return response()->json([
            'message' => 'Staff earnings fetched successfully.',
            'data' => [
                'report' => $report,
            ],
        ]);
    }

    public function presets(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'staff_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        $staff = StaffEarningsAccess::resolveTargetStaff(
            $user,
            isset($validated['staff_id']) ? (int) $validated['staff_id'] : null,
        );

        $presets = $this->earnings->presets($staff, $user);

        return response()->json([
            'message' => 'Staff earnings presets fetched successfully.',
            'data' => [
                'staff' => [
                    'id' => $staff->id,
                    'name' => $staff->name,
                ],
                'presets' => $presets,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function filterReport(array $report, string $search): array
    {
        $needle = strtolower(trim($search));
        $lines = array_values(array_filter(
            $report['lines'] ?? [],
            function (array $line) use ($needle): bool {
                $haystack = strtolower(implode(' ', array_filter([
                    $line['service_name'] ?? '',
                    $line['customer']['name'] ?? '',
                    $line['customer']['phone'] ?? '',
                    $line['status'] ?? '',
                ])));

                return str_contains($haystack, $needle);
            },
        ));

        $totalRevenue = array_sum(array_column($lines, 'revenue'));
        $totalCommission = array_sum(array_column($lines, 'commission'));
        $totalCollected = array_sum(array_column($lines, 'collected'));
        $totalMinutes = 0;
        $completedIds = [];
        $days = [];

        foreach ($lines as $line) {
            $minutes = (int) ($line['duration_minutes'] ?? 0);
            $totalMinutes += $minutes;
            if (($line['status'] ?? null) === 'completed' && isset($line['appointment_id'])) {
                $completedIds[(int) $line['appointment_id']] = true;
            }

            $dayKey = (string) ($line['date'] ?? 'unknown');
            if (! isset($days[$dayKey])) {
                $days[$dayKey] = [
                    'date' => $dayKey,
                    'services' => 0,
                    'minutes_worked' => 0,
                    'revenue' => 0.0,
                    'commission' => 0.0,
                    'collected' => 0.0,
                ];
            }

            $days[$dayKey]['services']++;
            $days[$dayKey]['minutes_worked'] += $minutes;
            $days[$dayKey]['revenue'] += (float) ($line['revenue'] ?? 0);
            $days[$dayKey]['commission'] += (float) ($line['commission'] ?? 0);
            $days[$dayKey]['collected'] += (float) ($line['collected'] ?? 0);
        }

        ksort($days);

        $report['lines'] = $lines;
        $report['days'] = array_values(array_map(static function (array $day): array {
            $day['revenue'] = round($day['revenue'], 2);
            $day['commission'] = round($day['commission'], 2);
            $day['collected'] = round($day['collected'], 2);
            $day['hours_worked'] = round($day['minutes_worked'] / 60, 1);

            return $day;
        }, $days));
        $report['summary']['appointments_completed'] = count($completedIds);
        $report['summary']['services_performed'] = count($lines);
        $report['summary']['minutes_worked'] = $totalMinutes;
        $report['summary']['hours_worked'] = round($totalMinutes / 60, 1);
        $report['summary']['revenue_generated'] = round($totalRevenue, 2);
        $report['summary']['collected'] = round($totalCollected, 2);
        $report['summary']['commission_earned'] = round($totalCommission, 2);

        return $report;
    }
}

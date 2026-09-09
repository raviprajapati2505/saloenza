<?php

namespace App\Services\Queue;

use App\Models\Appointment;
use App\Models\User;
use App\Support\Appointment\AppointmentStatus;
use App\Support\Customer\CustomerContactPayload;
use App\Support\Queue\QueueAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LiveQueueService
{
    private const QUEUE_STATUSES = [
        AppointmentStatus::SCHEDULED,
        AppointmentStatus::CONFIRMED,
        AppointmentStatus::IN_PROGRESS,
    ];

    /**
     * @return array<string, mixed>
     */
    public function snapshot(User $actor, ?int $branchId = null, ?Carbon $day = null): array
    {
        $day = ($day ?? now())->copy()->startOfDay();
        $dayEnd = $day->copy()->endOfDay();
        $saloonId = (int) $actor->saloon_id;

        if ($actor->isBranchScopedActor() && $actor->branch_id) {
            $branchId = (int) $actor->branch_id;
        }

        $staffFilterId = QueueAccess::staffFilterId($actor);

        $appointments = Appointment::query()
            ->with(['customer', 'staff', 'service', 'services.service', 'services.staff', 'branch'])
            ->where('saloon_id', $saloonId)
            ->when($branchId !== null, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when($staffFilterId !== null, function (Builder $query) use ($staffFilterId): void {
                $query->where(function (Builder $inner) use ($staffFilterId): void {
                    $inner->where('staff_id', $staffFilterId)
                        ->orWhereHas('services', fn (Builder $services) => $services->where('staff_id', $staffFilterId));
                });
            })
            ->whereBetween('starts_at', [$day, $dayEnd])
            ->whereIn('status', self::QUEUE_STATUSES)
            ->orderBy('starts_at')
            ->orderBy('created_at')
            ->get();

        $inService = [];
        $waiting = [];
        $upcoming = [];
        $walkIns = [];
        $posEntries = [];

        $now = now();

        foreach ($appointments as $appointment) {
            $item = $this->mapQueueItem($appointment, $actor, $now, $staffFilterId);

            if ($appointment->type === Appointment::TYPE_WALK_IN) {
                $walkIns[] = $item;
            }

            if (in_array($appointment->type, [Appointment::TYPE_WALK_IN, Appointment::TYPE_PRODUCT_SALE], true)) {
                $posEntries[] = $item;
            }

            if ($appointment->status === AppointmentStatus::IN_PROGRESS) {
                $inService[] = $item;
            } elseif (
                in_array($appointment->status, [AppointmentStatus::SCHEDULED, AppointmentStatus::CONFIRMED], true)
                && $appointment->type !== Appointment::TYPE_WALK_IN
            ) {
                $bucket = AppointmentStatus::queueBucket($appointment->status, $appointment->starts_at, $now);

                if ($bucket === 'waiting') {
                    $waiting[] = $item;
                } elseif ($bucket === 'upcoming') {
                    $upcoming[] = $item;
                }
            }
        }

        $ordered = $this->buildServingOrder($inService, $waiting, $upcoming, $walkIns);
        $avgDuration = $this->averageDurationMinutes($appointments);
        $walkInWaiting = $this->walkInsAwaitingService($walkIns);

        foreach ($ordered as $index => &$entry) {
            $entry['queue_position'] = $index + 1;
            $entry['estimated_wait_minutes'] = $this->waitMinutesBefore($ordered, $index, $avgDuration);
        }
        unset($entry);

        return [
            'generated_at' => now()->toISOString(),
            'date' => $day->toDateString(),
            'branch_id' => $branchId,
            'scope' => QueueAccess::scopeMeta($actor),
            'summary' => [
                'in_service' => count($inService),
                'waiting' => count($waiting),
                'upcoming' => count($upcoming),
                'walk_ins' => count($walkInWaiting),
                'walk_ins_total' => count($walkIns),
                'pos' => count($posEntries),
                'total_active' => count($ordered),
            ],
            'sections' => [
                'in_service' => $this->withQueueMeta($inService, $avgDuration),
                'waiting' => $this->withQueueMeta($waiting, $avgDuration),
                'upcoming' => $this->withQueueMeta($upcoming, $avgDuration),
                'walk_ins' => $this->withQueueMeta($walkInWaiting, $avgDuration),
                'pos' => $this->withQueueMeta($posEntries, $avgDuration),
            ],
            'queue' => $ordered,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Appointment>  $appointments
     */
    private function averageDurationMinutes($appointments): int
    {
        $durations = [];

        foreach ($appointments as $appointment) {
            if ($appointment->starts_at && $appointment->ends_at) {
                $durations[] = max(15, (int) $appointment->starts_at->diffInMinutes($appointment->ends_at));
            } elseif ($appointment->services->isNotEmpty()) {
                $sum = $appointment->services->sum(fn ($line) => (int) ($line->duration_minutes ?? 30));
                $durations[] = max(15, $sum ?: 30);
            }
        }

        if ($durations === []) {
            return 30;
        }

        return (int) round(array_sum($durations) / count($durations));
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function withQueueMeta(array $items, int $avgDuration): array
    {
        foreach ($items as $index => &$item) {
            $item['queue_position'] = $index + 1;
            $item['estimated_wait_minutes'] = $index * $avgDuration;
        }
        unset($item);

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $inService
     * @param  list<array<string, mixed>>  $waiting
     * @param  list<array<string, mixed>>  $upcoming
     * @param  list<array<string, mixed>>  $walkIns
     * @return list<array<string, mixed>>
     */
    private function buildServingOrder(array $inService, array $waiting, array $upcoming, array $walkIns): array
    {
        $sortByTime = static fn (array $a, array $b): int => strcmp((string) $a['starts_at'], (string) $b['starts_at']);

        usort($inService, $sortByTime);
        usort($waiting, $sortByTime);
        usort($upcoming, $sortByTime);

        $walkInWaiting = $this->walkInsAwaitingService($walkIns);
        usort($walkInWaiting, $sortByTime);

        return $this->uniqueQueueItems($inService, $waiting, $walkInWaiting, $upcoming);
    }

    /**
     * @param  list<array<string, mixed>>  $walkIns
     * @return list<array<string, mixed>>
     */
    private function walkInsAwaitingService(array $walkIns): array
    {
        return array_values(array_filter(
            $walkIns,
            fn (array $item): bool => in_array($item['status'], ['scheduled', 'confirmed'], true),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $ordered
     */
    private function waitMinutesBefore(array $ordered, int $index, int $avgDuration): int
    {
        if ($index <= 0) {
            return 0;
        }

        $wait = 0;

        for ($i = 0; $i < $index; $i++) {
            $item = $ordered[$i];
            $status = (string) ($item['status'] ?? '');

            if ($status === AppointmentStatus::IN_PROGRESS) {
                $wait += (int) ($item['duration_minutes'] ?? $avgDuration);
            } elseif (in_array($status, [AppointmentStatus::SCHEDULED, AppointmentStatus::CONFIRMED], true)) {
                $wait += (int) ($item['duration_minutes'] ?? $avgDuration);
            }
        }

        return $wait;
    }

    /**
     * @param  list<array<string, mixed>>  ...$groups
     * @return list<array<string, mixed>>
     */
    private function uniqueQueueItems(array ...$groups): array
    {
        $ordered = [];
        $seen = [];

        foreach ($groups as $group) {
            foreach ($group as $item) {
                $id = (int) ($item['id'] ?? 0);
                if ($id <= 0 || isset($seen[$id])) {
                    continue;
                }

                $seen[$id] = true;
                $ordered[] = $item;
            }
        }

        return $ordered;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapQueueItem(Appointment $appointment, User $actor, Carbon $now, ?int $forStaffId = null): array
    {
        $assignedLines = $this->assignedServiceLines($appointment, $forStaffId);

        $serviceNames = $assignedLines
            ->map(fn ($line) => $line->service?->name)
            ->filter()
            ->values()
            ->all();

        if ($serviceNames === [] && $appointment->service?->name) {
            $serviceNames = [$appointment->service->name];
        }

        $duration = 30;
        if ($forStaffId !== null && $assignedLines->isNotEmpty()) {
            $lineStarts = $assignedLines->pluck('starts_at')->filter();
            $lineEnds = $assignedLines->pluck('ends_at')->filter();

            if ($lineStarts->isNotEmpty() && $lineEnds->isNotEmpty()) {
                $duration = max(15, (int) $lineStarts->min()->diffInMinutes($lineEnds->max()));
            } else {
                $duration = max(15, (int) $assignedLines->sum(fn ($line) => (int) ($line->duration_minutes ?? 30)));
            }
        } elseif ($appointment->starts_at && $appointment->ends_at) {
            $duration = max(15, (int) $appointment->starts_at->diffInMinutes($appointment->ends_at));
        } elseif ($assignedLines->isNotEmpty()) {
            $duration = max(15, (int) $assignedLines->sum(fn ($line) => (int) ($line->duration_minutes ?? 30)));
        } elseif ($appointment->services->isNotEmpty()) {
            $duration = max(15, (int) $appointment->services->sum(fn ($line) => (int) ($line->duration_minutes ?? 30)));
        }

        $staffNames = ($forStaffId !== null ? $assignedLines : $appointment->services)
            ->map(fn ($line) => $line->staff?->name)
            ->filter()
            ->unique()
            ->values();

        if ($forStaffId !== null) {
            $staffId = $forStaffId;
            $staffName = $staffNames->first()
                ?? $appointment->services->firstWhere('staff_id', $forStaffId)?->staff?->name
                ?? 'Unassigned';
        } else {
            $staffName = $staffNames->isNotEmpty()
                ? $staffNames->join(', ')
                : ($appointment->staff?->name ?? 'Unassigned');
            $staffId = $staffNames->count() === 1
                ? ($appointment->services->firstWhere('staff_id', '!=', null)?->staff_id ?? $appointment->staff_id)
                : $appointment->staff_id;
        }

        $displayStartsAt = $forStaffId !== null && $assignedLines->isNotEmpty()
            ? ($assignedLines->pluck('starts_at')->filter()->min() ?? $appointment->starts_at)
            : $appointment->starts_at;

        $queueLane = AppointmentStatus::IN_PROGRESS === $appointment->status
            ? 'in_service'
            : ($appointment->type === Appointment::TYPE_WALK_IN
                ? 'walk_in_waiting'
                : AppointmentStatus::queueBucket($appointment->status, $appointment->starts_at, $now));

        $customer = CustomerContactPayload::identity($appointment->customer, $actor);
        if ($customer['name'] === null) {
            $customer['name'] = $appointment->isWalkIn() ? 'Walk-in' : 'Guest';
        }

        return [
            'id' => $appointment->id,
            'customer' => $customer,
            'services' => $serviceNames,
            'service_summary' => $serviceNames !== [] ? implode(', ', $serviceNames) : 'Service',
            'staff' => [
                'id' => $staffId,
                'name' => $staffName ?? 'Unassigned',
            ],
            'starts_at' => $displayStartsAt?->toISOString(),
            'ends_at' => $appointment->ends_at?->toISOString(),
            'status' => $appointment->status,
            'type' => $appointment->type,
            'queue_lane' => $queueLane,
            'booking_source' => $appointment->booking_source,
            'duration_minutes' => $duration,
            'payment_status' => $appointment->payment_status,
            'grand_total' => (float) ($appointment->grand_total ?? 0),
            'branch' => $appointment->branch ? [
                'id' => $appointment->branch->id,
                'name' => $appointment->branch->branch_name,
            ] : null,
            'minutes_until_start' => $displayStartsAt
                ? (int) $now->diffInMinutes($displayStartsAt, false)
                : null,
            'links' => [
                'customer' => $appointment->customer_id ? "/customers?highlight={$appointment->customer_id}" : null,
                'appointment' => "/appointments?highlight={$appointment->id}",
                'pos' => "/pos?appointment={$appointment->id}",
            ],
        ];
    }

    /**
     * @return Collection<int, \App\Models\AppointmentService>
     */
    private function assignedServiceLines(Appointment $appointment, ?int $forStaffId): Collection
    {
        if ($forStaffId === null) {
            return $appointment->services;
        }

        $lines = $appointment->services->filter(
            fn ($line): bool => (int) ($line->staff_id ?? 0) === $forStaffId,
        );

        if ($lines->isNotEmpty()) {
            return $lines->values();
        }

        if ((int) ($appointment->staff_id ?? 0) === $forStaffId) {
            return $appointment->services;
        }

        return collect();
    }
}

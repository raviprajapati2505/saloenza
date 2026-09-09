<?php

namespace App\Services\Appointment;

use App\Models\AppointmentService;
use App\Models\User;
use App\Support\Appointment\AppointmentStatus;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class StaffAvailabilityChecker
{
    private const WEEKDAY_KEYS = [
        0 => 'sunday',
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
    ];

    private const BLOCKING_STATUSES = [
        AppointmentStatus::SCHEDULED,
        AppointmentStatus::CONFIRMED,
        AppointmentStatus::IN_PROGRESS,
    ];

    /**
     * When a staff member has a weekly schedule, the slot must fall entirely within
     * their enabled working window for that day. No schedule means "follow salon hours".
     */
    public function isWithinWeeklySchedule(User $staff, CarbonInterface $startsAt, CarbonInterface $endsAt): bool
    {
        $schedule = $staff->weekly_schedule;

        if (! is_array($schedule) || $schedule === []) {
            return true;
        }

        $dayKey = self::WEEKDAY_KEYS[(int) $startsAt->dayOfWeek] ?? strtolower($startsAt->format('l'));
        $day = $schedule[$dayKey] ?? null;

        if (! is_array($day) || empty($day['enabled'])) {
            return false;
        }

        $open = (string) ($day['open'] ?? '09:00');
        $close = (string) ($day['close'] ?? '21:00');

        $dayStart = $startsAt->copy()->startOfDay();
        $openAt = $dayStart->copy()->setTimeFromTimeString($open);
        $closeAt = $dayStart->copy()->setTimeFromTimeString($close);

        return $startsAt->gte($openAt) && $endsAt->lte($closeAt);
    }

    /**
     * @param  list<array{staff_id: int|null, starts_at: CarbonInterface|string, ends_at: CarbonInterface|string}>  $lines
     */
    public function assertAvailable(array $lines, ?int $ignoreAppointmentId = null): void
    {
        $this->assertNoIntraBatchConflicts($lines);

        /** @var array<int, User|null> $staffCache */
        $staffCache = [];

        foreach ($lines as $index => $line) {
            $staffId = $line['staff_id'] ?? null;
            if ($staffId === null) {
                continue;
            }

            $startsAt = $line['starts_at'];
            $endsAt = $line['ends_at'];

            $staff = $staffCache[$staffId] ??= User::query()->find($staffId);

            if ($staff !== null && ! $this->isWithinWeeklySchedule($staff, $startsAt, $endsAt)) {
                throw ValidationException::withMessages([
                    "services.{$index}.staff_id" => "{$staff->name} is not scheduled to work during this time.",
                ]);
            }

            $conflict = AppointmentService::query()
                ->where('staff_id', $staffId)
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->whereHas('appointment', function ($query) use ($ignoreAppointmentId): void {
                    $query->whereIn('status', self::BLOCKING_STATUSES);
                    if ($ignoreAppointmentId !== null) {
                        $query->where('id', '!=', $ignoreAppointmentId);
                    }
                })
                ->with(['appointment.customer', 'service'])
                ->first();

            if ($conflict === null) {
                continue;
            }

            $customer = $conflict->appointment?->customer?->name ?? 'another customer';
            $service = $conflict->service?->name ?? 'a service';
            $window = sprintf(
                '%s – %s',
                optional($conflict->starts_at)->format('H:i') ?? '?',
                optional($conflict->ends_at)->format('H:i') ?? '?',
            );

            throw ValidationException::withMessages([
                "services.{$index}.staff_id" => "Staff is already booked for {$service} with {$customer} ({$window}).",
            ]);
        }
    }

    /**
     * @param  list<array{staff_id: int|null, starts_at: CarbonInterface|string, ends_at: CarbonInterface|string}>  $lines
     */
    private function assertNoIntraBatchConflicts(array $lines): void
    {
        for ($i = 0; $i < count($lines); $i++) {
            $leftStaffId = $lines[$i]['staff_id'] ?? null;
            if ($leftStaffId === null) {
                continue;
            }

            $leftStart = $lines[$i]['starts_at'];
            $leftEnd = $lines[$i]['ends_at'];

            for ($j = $i + 1; $j < count($lines); $j++) {
                if (($lines[$j]['staff_id'] ?? null) !== $leftStaffId) {
                    continue;
                }

                $rightStart = $lines[$j]['starts_at'];
                $rightEnd = $lines[$j]['ends_at'];

                if ($leftStart < $rightEnd && $rightStart < $leftEnd) {
                    throw ValidationException::withMessages([
                        "services.{$j}.staff_id" => 'Staff is double-booked within this appointment.',
                    ]);
                }
            }
        }
    }
}

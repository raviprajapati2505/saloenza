<?php

namespace App\Services\Attendance;

use App\Models\AttendancePunch;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AttendanceService
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

    /**
     * @param  array{type: string, punched_at?: string|null, method?: string|null, notes?: string|null, geo_lat?: float|null, geo_lng?: float|null, user_id?: int|null, branch_id?: int|null}  $data
     */
    public function punch(User $actor, array $data): AttendancePunch
    {
        $type = $data['type'];
        if (! in_array($type, [AttendancePunch::TYPE_IN, AttendancePunch::TYPE_OUT], true)) {
            throw ValidationException::withMessages([
                'type' => 'Punch type must be in or out.',
            ]);
        }

        $targetUserId = isset($data['user_id']) ? (int) $data['user_id'] : (int) $actor->id;
        $punchedAt = isset($data['punched_at'])
            ? Carbon::parse($data['punched_at'])
            : now();

        return AttendancePunch::query()->create([
            'saloon_id' => (int) $actor->saloon_id,
            'branch_id' => $data['branch_id'] ?? $actor->branch_id,
            'user_id' => $targetUserId,
            'type' => $type,
            'punched_at' => $punchedAt,
            'method' => $data['method'] ?? 'app',
            'geo_lat' => $data['geo_lat'] ?? null,
            'geo_lng' => $data['geo_lng'] ?? null,
            'created_by' => (int) $actor->id,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * @param  array{type?: string, from_date: string, to_date: string, reason?: string|null}  $data
     */
    public function requestLeave(User $actor, array $data): LeaveRequest
    {
        $from = Carbon::parse($data['from_date'])->startOfDay();
        $to = Carbon::parse($data['to_date'])->startOfDay();

        if ($to->lt($from)) {
            throw ValidationException::withMessages([
                'to_date' => 'Leave end date must be on or after start date.',
            ]);
        }

        return LeaveRequest::query()->create([
            'saloon_id' => (int) $actor->saloon_id,
            'branch_id' => $actor->branch_id,
            'user_id' => (int) $actor->id,
            'type' => $data['type'] ?? 'annual',
            'status' => LeaveRequest::STATUS_PENDING,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'reason' => $data['reason'] ?? null,
        ]);
    }

    public function decideLeave(LeaveRequest $leave, User $approver, string $status, ?string $notes = null): LeaveRequest
    {
        if (! in_array($status, [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'status' => 'Leave decision must be approved or rejected.',
            ]);
        }

        if ($leave->status !== LeaveRequest::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => 'Only pending leave requests can be decided.',
            ]);
        }

        $leave->update([
            'status' => $status,
            'approver_id' => (int) $approver->id,
            'decided_at' => now(),
            'decision_notes' => $notes,
        ]);

        return $leave->fresh(['user', 'approver', 'branch']);
    }

    /**
     * Optional: expand weekly_schedule into concrete shift rows for a date range.
     * Does not mutate users.weekly_schedule (booking availability stays intact).
     *
     * @return Collection<int, Shift>
     */
    public function generateShiftsFromWeekly(User $actor, Carbon $from, Carbon $to, ?int $branchId = null): Collection
    {
        $staff = User::query()
            ->staff()
            ->where('saloon_id', $actor->saloon_id)
            ->where('is_active', true)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->get();

        $created = collect();
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $dayKey = self::WEEKDAY_KEYS[(int) $cursor->dayOfWeek] ?? strtolower($cursor->format('l'));

            foreach ($staff as $member) {
                $schedule = $member->weekly_schedule;
                if (! is_array($schedule) || $schedule === []) {
                    continue;
                }

                $day = $schedule[$dayKey] ?? null;
                if (! is_array($day) || empty($day['enabled'])) {
                    continue;
                }

                $open = (string) ($day['open'] ?? '09:00');
                $close = (string) ($day['close'] ?? '21:00');
                $startsAt = $cursor->copy()->setTimeFromTimeString($open);
                $endsAt = $cursor->copy()->setTimeFromTimeString($close);

                $exists = Shift::query()
                    ->where('user_id', $member->id)
                    ->where('starts_at', $startsAt)
                    ->where('ends_at', $endsAt)
                    ->exists();

                if ($exists) {
                    continue;
                }

                $created->push(Shift::query()->create([
                    'saloon_id' => (int) $actor->saloon_id,
                    'branch_id' => $member->branch_id,
                    'user_id' => (int) $member->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'source' => 'generated_from_weekly',
                    'status' => 'scheduled',
                    'break_minutes' => 0,
                ]));
            }

            $cursor->addDay();
        }

        return $created;
    }
}

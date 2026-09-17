<?php

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\SalonBookingLink;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\Service;
use App\Models\User;
use App\Services\Appointment\AppointmentBookingService;
use App\Services\Appointment\AppointmentNotificationService;
use App\Services\Appointment\StaffAvailabilityChecker;
use App\Support\Catalog\SalonOfferingResolver;
use App\Support\Role\RoleCodes;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicBookingService
{
    private const SLOT_INTERVAL_MINUTES = 15;

    private const DAY_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

    public function __construct(
        private readonly AppointmentBookingService $booking,
        private readonly StaffAvailabilityChecker $availability,
        private readonly AppointmentNotificationService $notifications,
        private readonly \App\Services\Tenant\TenantSettingsService $tenantSettings,
    ) {}

    public function resolveLink(string $token): SalonBookingLink
    {
        $link = SalonBookingLink::query()
            ->with(['saloon', 'branch'])
            ->where('token', $token)
            ->where('is_active', true)
            ->first();

        if ($link === null) {
            throw ValidationException::withMessages([
                'token' => 'This booking link is invalid or has been disabled.',
            ]);
        }

        if (! $link->saloon?->is_active) {
            throw ValidationException::withMessages([
                'token' => 'Online booking is not available for this salon.',
            ]);
        }

        return $link;
    }

    /**
     * @return array<string, mixed>
     */
    public function bootstrap(SalonBookingLink $link): array
    {
        $salon = $link->saloon;
        $branchId = $link->branch_id;

        $branches = SaloonBranch::query()
            ->where('saloon_id', $salon->id)
            ->when($branchId !== null, fn ($query) => $query->where('id', $branchId))
            ->where('is_active', true)
            ->orderBy('branch_name')
            ->get(['id', 'branch_name'])
            ->map(fn (SaloonBranch $branch): array => [
                'id' => $branch->id,
                'name' => $branch->branch_name,
            ])
            ->values()
            ->all();

        $services = $this->publicBookableServices($salon->id, $branchId);

        return [
            'salon' => [
                'id' => $salon->id,
                'name' => $salon->name,
                'phone' => $salon->phone,
                'working_hours' => $salon->working_hours ?? [],
            ],
            'regional' => $this->tenantSettings->regionalPayload($salon),
            'link' => [
                'token' => $link->token,
                'label' => $link->label,
                'branch_id' => $link->branch_id,
            ],
            'branches' => $branches,
            'services' => $services,
        ];
    }

    /**
     * @param  list<int>  $serviceIds
     * @return array<string, mixed>
     */
    public function availableSlots(SalonBookingLink $link, string $date, array $serviceIds, ?int $branchId = null): array
    {
        if ($serviceIds === []) {
            throw ValidationException::withMessages([
                'service_ids' => 'Select at least one service.',
            ]);
        }

        $branchId = $this->resolveBranchId($link, $branchId);
        $salon = $link->saloon;
        $day = Carbon::parse($date)->startOfDay();

        if ($day->lt(now()->startOfDay())) {
            return ['date' => $date, 'slots' => []];
        }

        [$open, $close] = $this->dayHours($salon, $day);
        $services = $this->normalizeSelectedServices($salon->id, $branchId, $serviceIds);
        $totalDuration = array_sum(array_column($services, 'duration_minutes'));

        $slots = [];
        $cursor = $day->copy()->setTimeFromTimeString($open);
        $closing = $day->copy()->setTimeFromTimeString($close);

        while ($cursor->copy()->addMinutes($totalDuration)->lte($closing)) {
            if ($day->isSameDay(now()) && $cursor->lt(now())) {
                $cursor->addMinutes(self::SLOT_INTERVAL_MINUTES);
                continue;
            }

            if ($this->assignStaffToServices($salon->id, $branchId, $services, $cursor) !== null) {
                $slots[] = [
                    'starts_at' => $cursor->toISOString(),
                    'ends_at' => $cursor->copy()->addMinutes($totalDuration)->toISOString(),
                    'label' => $cursor->format('g:i A'),
                ];
            }

            $cursor->addMinutes(self::SLOT_INTERVAL_MINUTES);
        }

        return [
            'date' => $date,
            'duration_minutes' => $totalDuration,
            'slots' => $slots,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function submit(SalonBookingLink $link, array $payload): Appointment
    {
        return DB::transaction(function () use ($link, $payload): Appointment {
            $branchId = $this->resolveBranchId($link, isset($payload['branch_id']) ? (int) $payload['branch_id'] : null);
            $serviceIds = array_values(array_unique(array_map('intval', $payload['service_ids'] ?? [])));
            $startsAt = Carbon::parse($payload['starts_at']);

            $services = $this->normalizeSelectedServices($link->saloon_id, $branchId, $serviceIds);
            $servicePayload = $this->assignStaffToServices($link->saloon_id, $branchId, $services, $startsAt);

            if ($servicePayload === null) {
                throw ValidationException::withMessages([
                    'starts_at' => 'That time slot is no longer available. Please choose another time.',
                ]);
            }

            $owner = $this->bookingActor($link->saloon_id);

            $appointmentPayload = [
                'saloon_id' => $link->saloon_id,
                'branch_id' => $branchId,
                'starts_at' => $startsAt->toISOString(),
                'status' => 'confirmed',
                'type' => Appointment::TYPE_APPOINTMENT,
                'booking_source' => 'self_booking',
                'customer_name' => trim((string) ($payload['customer_name'] ?? '')),
                'customer_phone' => trim((string) ($payload['customer_phone'] ?? '')),
                'customer_email' => trim((string) ($payload['customer_email'] ?? '')),
                'notes' => filled($payload['notes'] ?? null)
                    ? trim((string) $payload['notes'])
                    : 'Booked via customer self-booking link',
                'services' => $servicePayload,
            ];

            $appointmentPayload['customer_id'] = $this->booking->resolveCustomerId(
                $appointmentPayload,
                (int) $link->saloon_id,
                null,
                null,
            );

            if ($appointmentPayload['customer_id'] !== null && filled($payload['customer_email'] ?? null)) {
                Customer::query()
                    ->where('id', $appointmentPayload['customer_id'])
                    ->update(['email' => trim((string) $payload['customer_email'])]);
            }

            $appointment = $this->booking->create($owner, $appointmentPayload);
            $this->notifications->appointmentCreated($appointment);

            return $appointment->fresh($this->booking->relations());
        });
    }

    private function resolveBranchId(SalonBookingLink $link, ?int $branchId): int
    {
        if ($link->branch_id !== null) {
            return (int) $link->branch_id;
        }

        if ($branchId !== null) {
            $branch = SaloonBranch::query()
                ->where('saloon_id', $link->saloon_id)
                ->where('id', $branchId)
                ->where('is_active', true)
                ->first();

            if ($branch === null) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Selected branch is not available.',
                ]);
            }

            return (int) $branch->id;
        }

        $defaultBranch = SaloonBranch::query()
            ->where('saloon_id', $link->saloon_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($defaultBranch === null) {
            throw ValidationException::withMessages([
                'branch_id' => 'No active branch is configured for online booking.',
            ]);
        }

        return (int) $defaultBranch->id;
    }

    /**
     * @param  list<int>  $serviceIds
     * @return list<array{service_id: int, price: float, duration_minutes: int}>
     */
    private function normalizeSelectedServices(int $saloonId, int $branchId, array $serviceIds): array
    {
        $normalized = [];

        foreach ($serviceIds as $serviceId) {
            $offering = SalonOfferingResolver::find($saloonId, (int) $serviceId, null, $branchId);

            if ($offering === null) {
                throw ValidationException::withMessages([
                    'service_ids' => 'One or more selected services are unavailable.',
                ]);
            }

            $normalized[] = [
                'service_id' => (int) $serviceId,
                'price' => (float) $offering->price,
                'duration_minutes' => max(1, (int) $offering->duration_minutes),
            ];
        }

        return $normalized;
    }

    /**
     * @return list<array{id: int, name: string, category: string|null, price: float, duration_minutes: int}>
     */
    private function publicBookableServices(int $saloonId, ?int $branchId): array
    {
        $catalog = SalonOfferingResolver::bookableServices($saloonId, $branchId);

        if ($catalog === []) {
            return [];
        }

        $serviceModels = Service::query()
            ->with('category')
            ->whereIn('id', collect($catalog)->pluck('service_id'))
            ->get()
            ->keyBy('id');

        return collect($catalog)
            ->map(function (array $row) use ($serviceModels): array {
                $service = $serviceModels->get($row['service_id']);

                return [
                    'id' => (int) $row['service_id'],
                    'name' => (string) ($row['name'] ?? $service?->name ?? 'Service'),
                    'category' => $service?->category?->name,
                    'price' => (float) $row['price'],
                    'duration_minutes' => (int) $row['duration_minutes'],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Assigns each service line to an available staff member (supports multi-staff combos).
     *
     * @param  list<array{service_id: int, price: float, duration_minutes: int}>  $services
     * @return list<array{service_id: int, staff_id: int, price: float, duration_minutes: int, sort_order: int}>|null
     */
    private function assignStaffToServices(int $saloonId, int $branchId, array $services, Carbon $startsAt): ?array
    {
        $staffMembers = User::query()
            ->staff()
            ->where('saloon_id', $saloonId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'weekly_schedule']);

        if ($staffMembers->isEmpty()) {
            return null;
        }

        $cursor = $startsAt->copy();
        $timeline = [];
        $payload = [];

        foreach ($services as $index => $service) {
            $lineStart = $cursor->copy();
            $lineEnd = $lineStart->copy()->addMinutes($service['duration_minutes']);
            $assignedStaffId = null;

            foreach ($staffMembers as $member) {
                if (! $this->availability->isWithinWeeklySchedule($member, $lineStart, $lineEnd)) {
                    continue;
                }

                $candidateLine = [
                    'staff_id' => (int) $member->id,
                    'starts_at' => $lineStart,
                    'ends_at' => $lineEnd,
                ];

                try {
                    $this->availability->assertAvailable([...$timeline, $candidateLine]);
                } catch (ValidationException) {
                    continue;
                }

                $assignedStaffId = (int) $member->id;
                $timeline[] = $candidateLine;
                break;
            }

            if ($assignedStaffId === null) {
                return null;
            }

            $payload[] = [
                'service_id' => $service['service_id'],
                'staff_id' => $assignedStaffId,
                'price' => $service['price'],
                'duration_minutes' => $service['duration_minutes'],
                'sort_order' => $index,
            ];

            $cursor = $lineEnd->copy();
        }

        return $payload;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dayHours(Saloon $salon, Carbon $day): array
    {
        $hours = $salon->working_hours ?? [];
        $key = self::DAY_KEYS[(int) $day->dayOfWeek];
        $window = $hours[$key] ?? $hours[strtolower($day->format('D'))] ?? null;

        if (is_array($window) && count($window) >= 2) {
            return [(string) $window[0], (string) $window[1]];
        }

        return ['09:00', '21:00'];
    }

    private function bookingActor(int $saloonId): User
    {
        $owner = User::query()
            ->where('saloon_id', $saloonId)
            ->whereHas('role', fn ($query) => $query->whereIn('code', [
                RoleCodes::SALON_FRANCHISE_OWNER,
                RoleCodes::SALON_FRANCHISE_MANAGER,
                RoleCodes::SALON_BRANCH_MANAGER,
            ]))
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($owner !== null) {
            return $owner;
        }

        $fallback = User::query()
            ->where('saloon_id', $saloonId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($fallback === null) {
            throw ValidationException::withMessages([
                'salon' => 'Salon is not configured for online booking.',
            ]);
        }

        return $fallback;
    }
}

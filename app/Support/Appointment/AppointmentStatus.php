<?php

namespace App\Support\Appointment;

use App\Models\Appointment;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final class AppointmentStatus
{
    public const SCHEDULED = 'scheduled';

    public const CONFIRMED = 'confirmed';

    public const IN_PROGRESS = 'in-progress';

    public const COMPLETED = 'completed';

    public const CANCELLED = 'cancelled';

    public const NO_SHOW = 'no-show';

    /** Minutes before start time that staff may mark a booking in service. */
    public const EARLY_START_GRACE_MINUTES = 15;

    /** Minutes before start when a booking appears in the waiting bucket. */
    public const WAITING_WINDOW_MINUTES = 15;

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $services
     */
    public static function defaultForCreate(array $payload, array $services = []): string
    {
        $type = $payload['type'] ?? ($services === [] ? Appointment::TYPE_PRODUCT_SALE : Appointment::TYPE_APPOINTMENT);

        if (in_array($type, [Appointment::TYPE_WALK_IN, Appointment::TYPE_PRODUCT_SALE], true)) {
            return self::COMPLETED;
        }

        try {
            $startsAt = Carbon::parse($payload['starts_at'] ?? now());

            if ($startsAt->isFuture()) {
                return self::SCHEDULED;
            }

            if ($startsAt->copy()->startOfDay()->lt(now()->startOfDay())) {
                return self::COMPLETED;
            }

            return self::CONFIRMED;
        } catch (\Throwable) {
            return self::SCHEDULED;
        }
    }

    public static function assertValidForTimeline(string $status, CarbonInterface $startsAt, string $type): void
    {
        if ($status !== self::IN_PROGRESS) {
            return;
        }

        if (in_array($type, [Appointment::TYPE_WALK_IN, Appointment::TYPE_PRODUCT_SALE], true)) {
            return;
        }

        $earliestStart = now()->copy()->addMinutes(self::EARLY_START_GRACE_MINUTES);

        if ($startsAt->gt($earliestStart)) {
            throw ValidationException::withMessages([
                'status' => 'Cannot start a service before the appointment time. Wait until the slot is due or adjust the start time.',
            ]);
        }
    }

    /**
     * @return 'in_service'|'waiting'|'upcoming'|null
     */
    public static function queueBucket(string $status, ?CarbonInterface $startsAt, ?CarbonInterface $now = null): ?string
    {
        $now ??= now();

        if ($status === self::IN_PROGRESS) {
            return 'in_service';
        }

        if (! in_array($status, [self::SCHEDULED, self::CONFIRMED], true)) {
            return null;
        }

        if ($startsAt === null) {
            return 'waiting';
        }

        if ($startsAt->lte($now->copy()->addMinutes(self::WAITING_WINDOW_MINUTES))) {
            return 'waiting';
        }

        return 'upcoming';
    }

    public static function isBlocking(string $status): bool
    {
        return in_array($status, [self::SCHEDULED, self::CONFIRMED, self::IN_PROGRESS], true);
    }
}

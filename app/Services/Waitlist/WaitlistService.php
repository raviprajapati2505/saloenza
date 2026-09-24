<?php

namespace App\Services\Waitlist;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WaitlistOffer;
use App\Support\Waitlist\WaitlistEntryStatus;
use App\Support\Waitlist\WaitlistOfferStatus;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WaitlistService
{
    public function list(
        int $saloonId,
        ?int $branchId = null,
        ?string $status = null,
        int $perPage = 25,
    ): LengthAwarePaginator {
        return WaitlistEntry::query()
            ->with([
                'customer:id,name,phone,email',
                'service:id,name',
                'preferredStaff:id,name',
                'branch:id,branch_name',
                'creator:id,name',
                'offers' => fn ($q) => $q->orderByDesc('id')->limit(5),
            ])
            ->where('saloon_id', $saloonId)
            ->when($branchId !== null, function (Builder $query) use ($branchId): void {
                $query->where(function (Builder $inner) use ($branchId): void {
                    $inner->whereNull('branch_id')->orWhere('branch_id', $branchId);
                });
            })
            ->when($status !== null && $status !== '', fn (Builder $q) => $q->where('status', $status))
            ->orderByDesc('priority')
            ->orderBy('created_at')
            ->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function addEntry(User $actor, int $saloonId, array $payload): WaitlistEntry
    {
        $customerId = (int) ($payload['customer_id'] ?? 0);
        $customer = Customer::query()->find($customerId);

        if ($customer === null || ! $customer->belongsToSaloon($saloonId)) {
            throw ValidationException::withMessages([
                'customer_id' => 'Customer is required and must belong to this salon.',
            ]);
        }

        $branchId = isset($payload['branch_id']) ? (int) $payload['branch_id'] : null;
        if ($actor->isBranchScopedActor() && $actor->branch_id) {
            $branchId = (int) $actor->branch_id;
        }

        return WaitlistEntry::query()->create([
            'saloon_id' => $saloonId,
            'branch_id' => $branchId,
            'customer_id' => $customerId,
            'service_id' => isset($payload['service_id']) ? (int) $payload['service_id'] : null,
            'preferred_staff_id' => isset($payload['preferred_staff_id']) ? (int) $payload['preferred_staff_id'] : null,
            'earliest_at' => $payload['earliest_at'] ?? null,
            'latest_at' => $payload['latest_at'] ?? null,
            'preferred_days' => $payload['preferred_days'] ?? null,
            'priority' => (int) ($payload['priority'] ?? 0),
            'status' => WaitlistEntryStatus::WAITING,
            'notes' => $payload['notes'] ?? null,
            'created_by' => $actor->id,
            'expires_at' => $payload['expires_at'] ?? null,
        ])->load([
            'customer:id,name,phone,email',
            'service:id,name',
            'preferredStaff:id,name',
            'branch:id,branch_name',
        ]);
    }

    public function cancelEntry(WaitlistEntry $entry): WaitlistEntry
    {
        return DB::transaction(function () use ($entry): WaitlistEntry {
            $entry->update(['status' => WaitlistEntryStatus::CANCELLED]);
            WaitlistOffer::query()
                ->where('waitlist_entry_id', $entry->id)
                ->where('status', WaitlistOfferStatus::PENDING)
                ->update(['status' => WaitlistOfferStatus::REVOKED]);

            return $entry->fresh([
                'customer:id,name,phone,email',
                'service:id,name',
                'preferredStaff:id,name',
                'branch:id,branch_name',
            ]);
        });
    }

    /**
     * Match waitlist when a slot is released (e.g. appointment cancelled).
     *
     * @return Collection<int, WaitlistOffer>
     */
    public function onSlotReleased(Appointment $appointment, int $offerTtlMinutes = 30): Collection
    {
        return $this->createOffersFromSlot([
            'saloon_id' => (int) $appointment->saloon_id,
            'branch_id' => $appointment->branch_id !== null ? (int) $appointment->branch_id : null,
            'service_id' => $appointment->service_id !== null ? (int) $appointment->service_id : null,
            'staff_id' => $appointment->staff_id !== null ? (int) $appointment->staff_id : null,
            'slot_starts_at' => $appointment->starts_at,
            'slot_ends_at' => $appointment->ends_at,
            'source_appointment_id' => (int) $appointment->id,
        ], $offerTtlMinutes);
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return Collection<int, WaitlistOffer>
     */
    public function createOffersFromSlot(array $slot, int $offerTtlMinutes = 30, int $maxOffers = 1): Collection
    {
        $saloonId = (int) $slot['saloon_id'];
        $branchId = isset($slot['branch_id']) ? (int) $slot['branch_id'] : null;
        $serviceId = isset($slot['service_id']) ? (int) $slot['service_id'] : null;
        $staffId = isset($slot['staff_id']) ? (int) $slot['staff_id'] : null;
        $startsAt = Carbon::parse($slot['slot_starts_at']);
        $endsAt = isset($slot['slot_ends_at']) && $slot['slot_ends_at']
            ? Carbon::parse($slot['slot_ends_at'])
            : null;

        $candidates = $this->matchingEntries($saloonId, $branchId, $serviceId, $staffId, $startsAt)
            ->take(max(1, $maxOffers));

        $offers = collect();

        foreach ($candidates as $entry) {
            $offers->push($this->createOffer($entry, [
                'source_appointment_id' => $slot['source_appointment_id'] ?? null,
                'slot_starts_at' => $startsAt,
                'slot_ends_at' => $endsAt,
                'staff_id' => $staffId ?? $entry->preferred_staff_id,
                'service_id' => $serviceId ?? $entry->service_id,
                'branch_id' => $branchId ?? $entry->branch_id,
                'channel' => $slot['channel'] ?? 'staff',
            ], $offerTtlMinutes));
        }

        return $offers;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createOffer(WaitlistEntry $entry, array $payload, int $offerTtlMinutes = 30): WaitlistOffer
    {
        if (! in_array($entry->status, [WaitlistEntryStatus::WAITING, WaitlistEntryStatus::OFFERED], true)) {
            throw ValidationException::withMessages([
                'entry' => 'Only waiting waitlist entries can receive offers.',
            ]);
        }

        return DB::transaction(function () use ($entry, $payload, $offerTtlMinutes): WaitlistOffer {
            $offer = WaitlistOffer::query()->create([
                'saloon_id' => (int) $entry->saloon_id,
                'waitlist_entry_id' => (int) $entry->id,
                'source_appointment_id' => $payload['source_appointment_id'] ?? null,
                'slot_starts_at' => $payload['slot_starts_at'],
                'slot_ends_at' => $payload['slot_ends_at'] ?? null,
                'staff_id' => $payload['staff_id'] ?? $entry->preferred_staff_id,
                'service_id' => $payload['service_id'] ?? $entry->service_id,
                'branch_id' => $payload['branch_id'] ?? $entry->branch_id,
                'status' => WaitlistOfferStatus::PENDING,
                'channel' => $payload['channel'] ?? 'staff',
                'offered_at' => now(),
                'expires_at' => now()->addMinutes(max(5, $offerTtlMinutes)),
            ]);

            $entry->update(['status' => WaitlistEntryStatus::OFFERED]);

            return $offer->load([
                'entry.customer:id,name,phone,email',
                'service:id,name',
                'staff:id,name',
                'branch:id,branch_name',
            ]);
        });
    }

    public function acceptOffer(WaitlistOffer $offer): WaitlistOffer
    {
        return DB::transaction(function () use ($offer): WaitlistOffer {
            $offer->refresh();

            if ($offer->status !== WaitlistOfferStatus::PENDING) {
                throw ValidationException::withMessages([
                    'offer' => 'Only pending offers can be accepted.',
                ]);
            }

            if ($offer->expires_at && $offer->expires_at->isPast()) {
                $offer->update(['status' => WaitlistOfferStatus::EXPIRED]);
                throw ValidationException::withMessages([
                    'offer' => 'This offer has expired.',
                ]);
            }

            $offer->update(['status' => WaitlistOfferStatus::ACCEPTED]);
            $offer->entry?->update(['status' => WaitlistEntryStatus::BOOKED]);

            WaitlistOffer::query()
                ->where('saloon_id', $offer->saloon_id)
                ->where('id', '!=', $offer->id)
                ->where('status', WaitlistOfferStatus::PENDING)
                ->where('slot_starts_at', $offer->slot_starts_at)
                ->when($offer->staff_id, fn ($q) => $q->where('staff_id', $offer->staff_id))
                ->update(['status' => WaitlistOfferStatus::REVOKED]);

            return $offer->fresh([
                'entry.customer:id,name,phone,email',
                'service:id,name',
                'staff:id,name',
                'branch:id,branch_name',
            ]);
        });
    }

    public function declineOffer(WaitlistOffer $offer, bool $offerNext = true): WaitlistOffer
    {
        return DB::transaction(function () use ($offer, $offerNext): WaitlistOffer {
            $offer->refresh();

            if ($offer->status !== WaitlistOfferStatus::PENDING) {
                throw ValidationException::withMessages([
                    'offer' => 'Only pending offers can be declined.',
                ]);
            }

            $offer->update(['status' => WaitlistOfferStatus::DECLINED]);
            $offer->entry?->update(['status' => WaitlistEntryStatus::WAITING]);

            if ($offerNext) {
                $this->createOffersFromSlot([
                    'saloon_id' => (int) $offer->saloon_id,
                    'branch_id' => $offer->branch_id,
                    'service_id' => $offer->service_id,
                    'staff_id' => $offer->staff_id,
                    'slot_starts_at' => $offer->slot_starts_at,
                    'slot_ends_at' => $offer->slot_ends_at,
                    'source_appointment_id' => $offer->source_appointment_id,
                    'channel' => $offer->channel,
                ]);
            }

            return $offer->fresh([
                'entry.customer:id,name,phone,email',
                'service:id,name',
                'staff:id,name',
                'branch:id,branch_name',
            ]);
        });
    }

    /**
     * @return Collection<int, WaitlistEntry>
     */
    public function matchingEntries(
        int $saloonId,
        ?int $branchId,
        ?int $serviceId,
        ?int $staffId,
        Carbon $slotStartsAt,
    ): Collection {
        return WaitlistEntry::query()
            ->where('saloon_id', $saloonId)
            ->where('status', WaitlistEntryStatus::WAITING)
            ->where(function (Builder $query) use ($slotStartsAt): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->when($branchId !== null, function (Builder $query) use ($branchId): void {
                $query->where(function (Builder $inner) use ($branchId): void {
                    $inner->whereNull('branch_id')->orWhere('branch_id', $branchId);
                });
            })
            ->when($serviceId !== null, function (Builder $query) use ($serviceId): void {
                $query->where(function (Builder $inner) use ($serviceId): void {
                    $inner->whereNull('service_id')->orWhere('service_id', $serviceId);
                });
            })
            ->when($staffId !== null, function (Builder $query) use ($staffId): void {
                $query->where(function (Builder $inner) use ($staffId): void {
                    $inner->whereNull('preferred_staff_id')->orWhere('preferred_staff_id', $staffId);
                });
            })
            ->where(function (Builder $query) use ($slotStartsAt): void {
                $query->whereNull('earliest_at')->orWhere('earliest_at', '<=', $slotStartsAt);
            })
            ->where(function (Builder $query) use ($slotStartsAt): void {
                $query->whereNull('latest_at')->orWhere('latest_at', '>=', $slotStartsAt);
            })
            ->where(function (Builder $query) use ($slotStartsAt): void {
                $query->whereNull('preferred_days')
                    ->orWhereJsonLength('preferred_days', 0)
                    ->orWhereJsonContains('preferred_days', (int) $slotStartsAt->dayOfWeek);
            })
            ->orderByDesc('priority')
            ->orderBy('created_at')
            ->get();
    }
}

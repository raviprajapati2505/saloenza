<?php

namespace App\Http\Controllers\Api\V1\Waitlist;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Waitlist\WaitlistEntryResource;
use App\Http\Resources\Api\V1\Waitlist\WaitlistOfferResource;
use App\Models\Appointment;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Models\WaitlistOffer;
use App\Services\Waitlist\WaitlistService;
use App\Support\Api\ListQuery;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use App\Support\Waitlist\WaitlistEntryStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WaitlistController extends Controller
{
    public function __construct(
        private readonly WaitlistService $waitlist,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'waitlist.view');

        $saloonId = TenantScope::resolveSaloonId($user, null);
        $validated = ListQuery::validate($request, [
            'status' => ['sometimes', 'string', Rule::in(WaitlistEntryStatus::all())],
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
        ]);

        $branchId = $user->isBranchScopedActor() && $user->branch_id
            ? (int) $user->branch_id
            : ($request->filled('branch_id') ? (int) $request->integer('branch_id') : null);

        $paginator = $this->waitlist->list(
            $saloonId,
            $branchId,
            $validated['status'] ?? null,
            (int) ($validated['per_page'] ?? 25),
        );

        return response()->json(ListQuery::responsePayload(
            'Waitlist fetched successfully.',
            'entries',
            $paginator,
            WaitlistEntryResource::class,
        ));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'waitlist.manage');

        $saloonId = TenantScope::resolveSaloonId($user, null);

        $validated = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(
                    fn ($query) => $query->where('saloon_id', $saloonId),
                ),
            ],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'preferred_staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'earliest_at' => ['nullable', 'date'],
            'latest_at' => ['nullable', 'date', 'after_or_equal:earliest_at'],
            'preferred_days' => ['nullable', 'array'],
            'preferred_days.*' => ['integer', 'min:0', 'max:6'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date'],
        ]);

        if (isset($validated['branch_id'])) {
            BranchScope::ensureSameBranch($user, (int) $validated['branch_id']);
        }

        $entry = $this->waitlist->addEntry($user, $saloonId, $validated);

        return response()->json([
            'message' => 'Waitlist entry created successfully.',
            'data' => ['entry' => (new WaitlistEntryResource($entry))->resolve()],
        ], 201);
    }

    public function destroy(Request $request, WaitlistEntry $waitlistEntry): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'waitlist.manage');
        TenantScope::ensureSaloonAccess($user, (int) $waitlistEntry->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $waitlistEntry->branch_id !== null ? (int) $waitlistEntry->branch_id : null,
        );

        $entry = $this->waitlist->cancelEntry($waitlistEntry);

        return response()->json([
            'message' => 'Waitlist entry cancelled.',
            'data' => ['entry' => (new WaitlistEntryResource($entry))->resolve()],
        ]);
    }

    public function createOffer(Request $request, WaitlistEntry $waitlistEntry): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'waitlist.manage');
        TenantScope::ensureSaloonAccess($user, (int) $waitlistEntry->saloon_id);

        $validated = $request->validate([
            'slot_starts_at' => ['required', 'date'],
            'slot_ends_at' => ['nullable', 'date', 'after:slot_starts_at'],
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'branch_id' => ['nullable', 'integer', 'exists:saloon_branches,id'],
            'offer_ttl_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'channel' => ['nullable', 'string', 'max:30'],
        ]);

        $offer = $this->waitlist->createOffer($waitlistEntry, $validated, (int) ($validated['offer_ttl_minutes'] ?? 30));

        return response()->json([
            'message' => 'Waitlist offer created.',
            'data' => ['offer' => (new WaitlistOfferResource($offer))->resolve()],
        ], 201);
    }

    public function acceptOffer(Request $request, WaitlistOffer $waitlistOffer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'waitlist.manage');
        TenantScope::ensureSaloonAccess($user, (int) $waitlistOffer->saloon_id);

        $offer = $this->waitlist->acceptOffer($waitlistOffer);

        return response()->json([
            'message' => 'Waitlist offer accepted. Create the appointment for this slot.',
            'data' => ['offer' => (new WaitlistOfferResource($offer))->resolve()],
        ]);
    }

    public function declineOffer(Request $request, WaitlistOffer $waitlistOffer): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'waitlist.manage');
        TenantScope::ensureSaloonAccess($user, (int) $waitlistOffer->saloon_id);

        $validated = $request->validate([
            'offer_next' => ['sometimes', 'boolean'],
        ]);

        $offer = $this->waitlist->declineOffer(
            $waitlistOffer,
            (bool) ($validated['offer_next'] ?? true),
        );

        return response()->json([
            'message' => 'Waitlist offer declined.',
            'data' => ['offer' => (new WaitlistOfferResource($offer))->resolve()],
        ]);
    }

    /**
     * Manual trigger when a cancellation frees a slot (also called from appointment update).
     */
    public function fromCancellation(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'waitlist.manage');

        $saloonId = TenantScope::resolveSaloonId($user, null);

        $validated = $request->validate([
            'appointment_id' => ['sometimes', 'integer', 'exists:appointments,id'],
            'slot_starts_at' => ['required_without:appointment_id', 'date'],
            'slot_ends_at' => ['nullable', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:saloon_branches,id'],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'staff_id' => ['nullable', 'integer', 'exists:users,id'],
            'offer_ttl_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'max_offers' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $ttl = (int) ($validated['offer_ttl_minutes'] ?? 30);
        $max = (int) ($validated['max_offers'] ?? 1);

        if (! empty($validated['appointment_id'])) {
            $appointment = Appointment::query()->findOrFail((int) $validated['appointment_id']);
            TenantScope::ensureSaloonAccess($user, (int) $appointment->saloon_id);
            $offers = $this->waitlist->onSlotReleased($appointment, $ttl);
        } else {
            $offers = $this->waitlist->createOffersFromSlot([
                'saloon_id' => $saloonId,
                'branch_id' => $validated['branch_id'] ?? null,
                'service_id' => $validated['service_id'] ?? null,
                'staff_id' => $validated['staff_id'] ?? null,
                'slot_starts_at' => $validated['slot_starts_at'],
                'slot_ends_at' => $validated['slot_ends_at'] ?? null,
            ], $ttl, $max);
        }

        return response()->json([
            'message' => 'Waitlist offers created from released slot.',
            'data' => [
                'offers' => WaitlistOfferResource::collection($offers)->resolve(),
            ],
        ], 201);
    }
}

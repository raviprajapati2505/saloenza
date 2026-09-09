<?php

namespace App\Http\Controllers\Api\V1\Booking;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Appointment\AppointmentResource;
use App\Models\SalonBookingLink;
use App\Models\User;
use App\Support\EnsuresPermission;
use App\Support\Branch\BranchScope;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingLinkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'booking_links.view');

        $saloonId = TenantScope::resolveSaloonId($user, null);

        $links = SalonBookingLink::query()
            ->with(['branch:id,branch_name', 'creator:id,name'])
            ->where('saloon_id', $saloonId)
            ->when($user->isBranchScopedActor() && $user->branch_id, function ($query) use ($user): void {
                $query->where(function ($inner) use ($user): void {
                    $inner->whereNull('branch_id')->orWhere('branch_id', $user->branch_id);
                });
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SalonBookingLink $link): array => $this->serializeLink($link));

        return response()->json([
            'message' => 'Booking links fetched successfully.',
            'data' => [
                'links' => $links,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'booking_links.manage');

        $saloonId = TenantScope::resolveSaloonId($user, null);

        $validated = $request->validate([
            'label' => ['nullable', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer', 'exists:saloon_branches,id'],
        ]);

        $branchId = isset($validated['branch_id']) ? (int) $validated['branch_id'] : null;

        if ($user->isBranchScopedActor() && $user->branch_id) {
            $branchId = (int) $user->branch_id;
        }

        if ($branchId !== null) {
            BranchScope::ensureSameBranch($user, $branchId, 'You can only create links for your branch.');
        }

        $link = SalonBookingLink::query()->create([
            'saloon_id' => $saloonId,
            'branch_id' => $branchId,
            'token' => SalonBookingLink::generateToken(),
            'label' => $validated['label'] ?? 'Customer booking link',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $link->load(['branch:id,branch_name', 'creator:id,name']);

        return response()->json([
            'message' => 'Booking link created successfully.',
            'data' => [
                'link' => $this->serializeLink($link),
            ],
        ], 201);
    }

    public function update(Request $request, SalonBookingLink $bookingLink): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'booking_links.manage');
        TenantScope::ensureSaloonAccess($user, (int) $bookingLink->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $bookingLink->branch_id !== null ? (int) $bookingLink->branch_id : null,
            'You can only manage booking links for your branch.',
        );

        $validated = $request->validate([
            'label' => ['sometimes', 'nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $bookingLink->update($validated);
        $bookingLink->load(['branch:id,branch_name', 'creator:id,name']);

        return response()->json([
            'message' => 'Booking link updated successfully.',
            'data' => [
                'link' => $this->serializeLink($bookingLink),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLink(SalonBookingLink $link): array
    {
        return [
            'id' => $link->id,
            'saloon_id' => $link->saloon_id,
            'branch_id' => $link->branch_id,
            'token' => $link->token,
            'label' => $link->label,
            'is_active' => (bool) $link->is_active,
            'url' => $link->publicUrl(),
            'path' => '/book/'.$link->token,
            'branch' => $link->branch ? [
                'id' => $link->branch->id,
                'name' => $link->branch->branch_name,
            ] : null,
            'creator' => $link->creator ? [
                'id' => $link->creator->id,
                'name' => $link->creator->name,
            ] : null,
            'created_at' => $link->created_at?->toISOString(),
        ];
    }
}

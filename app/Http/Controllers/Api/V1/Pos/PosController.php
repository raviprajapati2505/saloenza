<?php

namespace App\Http\Controllers\Api\V1\Pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Pos\PosCheckoutRequest;
use App\Http\Resources\Api\V1\Appointment\AppointmentResource;
use App\Models\User;
use App\Services\Appointment\AppointmentBookingService;
use App\Services\Pos\PosCatalogService;
use App\Services\Pos\PosCheckoutService;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosController extends Controller
{
    public function __construct(
        private readonly PosCatalogService $catalog,
        private readonly PosCheckoutService $checkout,
        private readonly AppointmentBookingService $booking,
    ) {
    }

    public function catalog(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'appointments.view');

        $validated = $request->validate([
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
        ]);

        $branchId = $user->isBranchScopedActor() && $user->branch_id
            ? (int) $user->branch_id
            : ($request->filled('branch_id') ? (int) $validated['branch_id'] : null);

        if ($branchId !== null && $user->isBranchScopedActor()) {
            BranchScope::ensureSameBranch(
                $user,
                $branchId,
                'You can only access POS for your current branch.',
            );
        }

        return response()->json([
            'message' => 'POS catalog fetched successfully.',
            'data' => [
                'catalog' => $this->catalog->catalog($user, $branchId),
            ],
        ]);
    }

    public function checkout(PosCheckoutRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'appointments.create');
        $this->booking->assertCanMutate($user);

        $payload = $request->validated();
        if ($user->isBranchScopedActor() && $user->branch_id) {
            BranchScope::ensureSameBranch(
                $user,
                (int) $payload['branch_id'],
                'You can only checkout for your current branch.',
            );
        }

        TenantScope::ensureSaloonAccess($user, (int) $user->saloon_id);

        $appointment = $this->checkout->checkout($user, $payload);

        return response()->json([
            'message' => 'Checkout completed successfully.',
            'data' => [
                'appointment' => (new AppointmentResource($appointment->load($this->booking->relations())))->resolve(),
            ],
        ], 201);
    }
}

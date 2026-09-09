<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Admin\AdminOnboardingAction;
use App\Actions\Admin\AdminOnboardingManagementAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreAdminOnboardingRequest;
use App\Http\Requests\Api\V1\Admin\UpdateAdminOnboardingRequest;
use App\Http\Resources\Api\V1\Admin\AdminOnboardingListItemResource;
use App\Http\Resources\Api\V1\Admin\AdminOnboardingResponseResource;
use App\Models\Saloon;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOnboardingController extends Controller
{
    public function __construct(
        private readonly AdminOnboardingAction $adminOnboardingAction,
        private readonly AdminOnboardingManagementAction $managementAction,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'onboarding_status' => ['sometimes', 'string', 'in:completed,pending,no_owner'],
        ]);

        $items = $this->managementAction->list($validated);

        $records = $items->map(function (array $item) {
            $saloon = $item['saloon'];
            $saloon->setAttribute('owner_user', $item['owner']);
            $saloon->setAttribute('onboarding_status', $item['onboarding_status']);
            $saloon->setAttribute('branch_count', $item['branch_count']);

            return $saloon;
        });

        return response()->json([
            'message' => 'Onboarding records fetched successfully.',
            'data' => [
                'onboardings' => AdminOnboardingListItemResource::collection($records)->resolve(),
                'summary' => [
                    'total' => $records->count(),
                    'completed' => $items->where('onboarding_status', 'completed')->count(),
                    'pending' => $items->where('onboarding_status', 'pending')->count(),
                    'no_owner' => $items->where('onboarding_status', 'no_owner')->count(),
                ],
            ],
        ]);
    }

    public function show(Saloon $saloon): JsonResponse
    {
        $result = $this->managementAction->show($saloon);

        return response()->json([
            'message' => 'Onboarding record fetched successfully.',
            'data' => (new AdminOnboardingResponseResource($result))->resolve(),
        ]);
    }

    public function store(StoreAdminOnboardingRequest $request): JsonResponse
    {
        $result = $this->adminOnboardingAction->execute($request->validated());

        return response()->json([
            'message' => 'Salon onboarded successfully.',
            'data' => (new AdminOnboardingResponseResource($result))->resolve(),
        ], 201);
    }

    public function update(UpdateAdminOnboardingRequest $request, Saloon $saloon): JsonResponse
    {
        $result = $this->adminOnboardingAction->update($saloon, $request->validated());

        return response()->json([
            'message' => 'Onboarding record updated successfully.',
            'data' => (new AdminOnboardingResponseResource($result))->resolve(),
        ]);
    }

    public function completeOwner(User $owner): JsonResponse
    {
        $user = $this->managementAction->completeOwner($owner);

        return response()->json([
            'message' => 'Owner onboarding marked as complete.',
            'data' => [
                'owner' => [
                    'id' => $user->id,
                    'onboarding_completed_at' => $user->onboarding_completed_at?->toISOString(),
                    'should_onboard' => false,
                ],
            ],
        ]);
    }

    public function resetOwner(User $owner): JsonResponse
    {
        $user = $this->managementAction->resetOwner($owner);

        return response()->json([
            'message' => 'Owner onboarding reset successfully.',
            'data' => [
                'owner' => [
                    'id' => $user->id,
                    'onboarding_completed_at' => null,
                    'should_onboard' => true,
                ],
            ],
        ]);
    }
}

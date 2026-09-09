<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tenant\UpdateTenantSettingsRequest;
use App\Http\Requests\Api\V1\Tenant\UploadSalonLogoRequest;
use App\Models\User;
use App\Services\Tenant\TenantSettingsService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SalonSettingsController extends Controller
{
    public function __construct(
        private readonly TenantSettingsService $tenantSettingsService,
    ) {
    }

    public function index(): JsonResponse
    {
        $salon = $this->resolveSalon();

        return response()->json([
            'message' => 'Salon settings fetched successfully.',
            'data' => [
                'groups' => $this->tenantSettingsService->getAllGroupsPayload($salon),
                'branding' => $this->tenantSettingsService->brandingPayload($salon),
            ],
        ]);
    }

    public function show(string $group): JsonResponse
    {
        $salon = $this->resolveSalon();

        try {
            $payload = $this->tenantSettingsService->getGroupPayload($salon, $group);
        } catch (\InvalidArgumentException) {
            throw new HttpException(404, 'Settings group not found.');
        }

        return response()->json([
            'message' => 'Salon settings group fetched successfully.',
            'data' => $payload,
        ]);
    }

    public function update(UpdateTenantSettingsRequest $request, string $group): JsonResponse
    {
        $salon = $this->resolveSalon();

        try {
            $payload = $this->tenantSettingsService->updateGroup(
                $salon,
                $group,
                $request->validatedSettings(),
                $request->user(),
            );
        } catch (\InvalidArgumentException) {
            throw new HttpException(404, 'Settings group not found.');
        }

        return response()->json([
            'message' => 'Salon settings updated successfully.',
            'data' => [
                'group' => $payload,
                'branding' => $this->tenantSettingsService->brandingPayload($salon->fresh()),
            ],
        ]);
    }

    public function uploadLogo(UploadSalonLogoRequest $request): JsonResponse
    {
        $salon = $this->resolveSalon();

        $payload = $this->tenantSettingsService->updateLogo(
            $salon,
            $request->file('logo'),
            $request->user(),
        );

        return response()->json([
            'message' => 'Salon logo updated successfully.',
            'data' => [
                'group' => $payload,
                'branding' => $this->tenantSettingsService->brandingPayload($salon->fresh()),
            ],
        ]);
    }

    public function deleteLogo(): JsonResponse
    {
        $salon = $this->resolveSalon();

        $payload = $this->tenantSettingsService->clearLogo($salon);

        return response()->json([
            'message' => 'Salon logo removed. Platform default will be used.',
            'data' => [
                'group' => $payload,
                'branding' => $this->tenantSettingsService->brandingPayload($salon->fresh()),
            ],
        ]);
    }

    private function resolveSalon(): \App\Models\Saloon
    {
        /** @var User $user */
        $user = request()->user();

        if ($user->saloon_id === null) {
            throw new AuthorizationException('Your account is not linked to a salon.');
        }

        $salon = $user->saloon;

        if ($salon === null) {
            throw new AuthorizationException('Salon not found.');
        }

        return $salon;
    }
}

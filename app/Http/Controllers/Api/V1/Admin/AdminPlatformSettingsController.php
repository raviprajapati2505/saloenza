<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdatePlatformSettingsRequest;
use App\Http\Requests\Api\V1\Admin\UploadPlatformLogoRequest;
use App\Services\Platform\PlatformSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AdminPlatformSettingsController extends Controller
{
    public function __construct(
        private readonly PlatformSettingsService $platformSettingsService,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()->json([
            'message' => 'Platform settings fetched successfully.',
            'data' => [
                'groups' => $this->platformSettingsService->getAllGroupsPayload(),
            ],
        ]);
    }

    public function show(string $group): JsonResponse
    {
        try {
            $payload = $this->platformSettingsService->getGroupPayload($group);
        } catch (\InvalidArgumentException) {
            throw new HttpException(404, 'Settings group not found.');
        }

        return response()->json([
            'message' => 'Platform settings group fetched successfully.',
            'data' => $payload,
        ]);
    }

    public function update(UpdatePlatformSettingsRequest $request, string $group): JsonResponse
    {
        try {
            $payload = $this->platformSettingsService->updateGroup(
                $group,
                $request->validatedSettings(),
                $request->user(),
            );
        } catch (\InvalidArgumentException) {
            throw new HttpException(404, 'Settings group not found.');
        }

        return response()->json([
            'message' => 'Platform settings updated successfully.',
            'data' => $payload,
        ]);
    }

    public function uploadLogo(UploadPlatformLogoRequest $request): JsonResponse
    {
        $payload = $this->platformSettingsService->updateLogo(
            $request->file('logo'),
            $request->user(),
        );

        return response()->json([
            'message' => 'Platform logo updated successfully.',
            'data' => [
                'group' => $payload,
                'branding' => $this->platformSettingsService->brandingPayload(),
            ],
        ]);
    }

    public function deleteLogo(): JsonResponse
    {
        $payload = $this->platformSettingsService->clearLogo();

        return response()->json([
            'message' => 'Platform logo removed. Default logo will be used.',
            'data' => [
                'group' => $payload,
                'branding' => $this->platformSettingsService->brandingPayload(),
            ],
        ]);
    }
}

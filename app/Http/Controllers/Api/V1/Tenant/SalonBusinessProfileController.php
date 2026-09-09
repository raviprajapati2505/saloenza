<?php

namespace App\Http\Controllers\Api\V1\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tenant\UpdateSalonBusinessProfileRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;

class SalonBusinessProfileController extends Controller
{
    public function show(): JsonResponse
    {
        $salon = $this->resolveSalon();

        return response()->json([
            'message' => 'Business profile fetched successfully.',
            'data' => [
                'name' => $salon->name,
                'phone' => $salon->phone,
                'whatsapp' => $salon->whatsapp,
                'gst_number' => $salon->gst_number,
                'address' => $salon->address,
                'city' => $salon->city,
                'state' => $salon->state,
                'working_hours' => $salon->working_hours ?? [],
            ],
        ]);
    }

    public function update(UpdateSalonBusinessProfileRequest $request): JsonResponse
    {
        $salon = $this->resolveSalon();
        $salon->update($request->validated());

        return response()->json([
            'message' => 'Business profile updated successfully.',
            'data' => [
                'name' => $salon->name,
                'phone' => $salon->phone,
                'whatsapp' => $salon->whatsapp,
                'gst_number' => $salon->gst_number,
                'address' => $salon->address,
                'city' => $salon->city,
                'state' => $salon->state,
                'working_hours' => $salon->working_hours ?? [],
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

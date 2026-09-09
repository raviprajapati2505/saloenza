<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Appointment\AppointmentResource;
use App\Services\Booking\PublicBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicBookingController extends Controller
{
    public function __construct(
        private readonly PublicBookingService $booking,
    ) {}

    public function show(string $token): JsonResponse
    {
        $link = $this->booking->resolveLink($token);

        return response()->json([
            'message' => 'Booking page loaded successfully.',
            'data' => $this->booking->bootstrap($link),
        ]);
    }

    public function slots(Request $request, string $token): JsonResponse
    {
        $link = $this->booking->resolveLink($token);

        $validated = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'branch_id' => ['nullable', 'integer', 'exists:saloon_branches,id'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'min:1'],
        ]);

        $slots = $this->booking->availableSlots(
            $link,
            $validated['date'],
            array_map('intval', $validated['service_ids']),
            isset($validated['branch_id']) ? (int) $validated['branch_id'] : null,
        );

        return response()->json([
            'message' => 'Available slots fetched successfully.',
            'data' => $slots,
        ]);
    }

    public function store(Request $request, string $token): JsonResponse
    {
        $link = $this->booking->resolveLink($token);

        $validated = $request->validate([
            'branch_id' => ['nullable', 'integer', 'exists:saloon_branches,id'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:190'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $appointment = $this->booking->submit($link, $validated);

        return response()->json([
            'message' => 'Your appointment has been confirmed.',
            'data' => [
                'appointment' => (new AppointmentResource($appointment))->resolve(),
            ],
        ], 201);
    }
}

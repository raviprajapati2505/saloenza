<?php

namespace App\Http\Controllers\Api\V1\Appointment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Appointment\CaptureAppointmentPaymentRequest;
use App\Http\Requests\Api\V1\Appointment\RefundAppointmentPaymentRequest;
use App\Http\Resources\Api\V1\Appointment\AppointmentResource;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Appointment\AppointmentNotificationService;
use App\Services\Appointment\AppointmentPaymentService;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppointmentPaymentController extends Controller
{
    public function __construct(
        private readonly AppointmentPaymentService $payments,
        private readonly AppointmentNotificationService $notifications,
    ) {}

    public function capture(CaptureAppointmentPaymentRequest $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'appointments.update');
        TenantScope::ensureSaloonAccess($user, (int) $appointment->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $appointment->branch_id !== null ? (int) $appointment->branch_id : null,
            'You can only collect payment for your current branch.',
        );

        $appointment = $this->payments->capture(
            $appointment,
            (float) $request->validated('amount'),
            (string) $request->validated('payment_method'),
        );

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'data' => [
                'appointment' => (new AppointmentResource($appointment))->resolve(),
            ],
        ]);
    }

    public function refund(RefundAppointmentPaymentRequest $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'appointments.update');
        TenantScope::ensureSaloonAccess($user, (int) $appointment->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $appointment->branch_id !== null ? (int) $appointment->branch_id : null,
            'You can only refund payment for your current branch.',
        );

        $amount = $request->filled('amount') ? (float) $request->validated('amount') : null;
        $appointment = $this->payments->refund($appointment, $amount);

        return response()->json([
            'message' => 'Payment refunded successfully.',
            'data' => [
                'appointment' => (new AppointmentResource($appointment))->resolve(),
            ],
        ]);
    }

    public function remind(Request $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'appointments.update');
        TenantScope::ensureSaloonAccess($user, (int) $appointment->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $appointment->branch_id !== null ? (int) $appointment->branch_id : null,
            'You can only send payment reminders for your current branch.',
        );

        try {
            $sent = $this->notifications->sendOutstandingPaymentReminder($appointment);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first()
                ?: $exception->getMessage()
                ?: 'Unable to send a payment reminder.';

            return response()->json(['message' => $message], 422);
        }

        if (! $sent) {
            return response()->json([
                'message' => 'Unable to deliver a payment reminder to this customer.',
            ], 422);
        }

        $appointment->update(['payment_reminder_sent_at' => now()]);

        return response()->json([
            'message' => 'Payment reminder sent.',
            'data' => [
                'appointment' => (new AppointmentResource($appointment->fresh()))->resolve(),
            ],
        ]);
    }
}

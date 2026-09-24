<?php

namespace App\Http\Controllers\Api\V1\NoShow;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Appointment\AppointmentResource;
use App\Http\Resources\Api\V1\NoShow\NoShowPolicyResource;
use App\Models\Appointment;
use App\Models\User;
use App\Services\NoShow\NoShowPolicyService;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\NoShow\NoShowPolicyApplyMode;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NoShowPolicyController extends Controller
{
    public function __construct(
        private readonly NoShowPolicyService $policies,
    ) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'payments.policy.manage');

        $saloonId = TenantScope::resolveSaloonId($user, null);
        $policy = $this->policies->getOrCreatePolicy($saloonId);

        return response()->json([
            'message' => 'No-show policy fetched successfully.',
            'data' => ['policy' => (new NoShowPolicyResource($policy))->resolve()],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'payments.policy.manage');

        $saloonId = TenantScope::resolveSaloonId($user, null);
        $policy = $this->policies->getOrCreatePolicy($saloonId);

        $validated = $request->validate([
            'is_enabled' => ['sometimes', 'boolean'],
            'apply_mode' => ['sometimes', 'string', Rule::in(NoShowPolicyApplyMode::all())],
            'min_no_shows' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'window_days' => ['sometimes', 'integer', 'min:7', 'max:365'],
            'protection_type' => ['sometimes', 'string', Rule::in(['deposit', 'full_prepay', 'card_on_file_auth'])],
            'deposit_type' => ['sometimes', 'string', Rule::in(['fixed', 'percent'])],
            'deposit_value' => ['sometimes', 'numeric', 'min:0', 'max:99999999'],
            'fee_type' => ['sometimes', 'string', Rule::in(['fixed', 'percent'])],
            'fee_value' => ['sometimes', 'numeric', 'min:0', 'max:99999999'],
            'cancel_cutoff_hours' => ['sometimes', 'integer', 'min:0', 'max:720'],
            'currency' => ['sometimes', 'nullable', 'string', 'max:10'],
        ]);

        $policy->update($validated);

        return response()->json([
            'message' => 'No-show policy updated successfully.',
            'data' => ['policy' => (new NoShowPolicyResource($policy->fresh()))->resolve()],
        ]);
    }

    public function evaluate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['payments.policy.manage', 'appointments.create', 'appointments.view']);

        $saloonId = TenantScope::resolveSaloonId($user, null);

        $validated = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'services_total' => ['required', 'numeric', 'min:0'],
        ]);

        $result = $this->policies->evaluateDepositRequirement(
            $saloonId,
            isset($validated['customer_id']) ? (int) $validated['customer_id'] : null,
            (float) $validated['services_total'],
        );

        return response()->json([
            'message' => 'Deposit requirement evaluated.',
            'data' => [
                'required' => $result['required'],
                'amount' => $result['amount'],
                'deposit_status' => $result['deposit_status'],
                'reason' => $result['reason'],
            ],
        ]);
    }

    public function markDepositPaid(Request $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'payments.charge');
        $this->assertAppointmentAccess($user, $appointment);

        $appointment = $this->policies->markDepositPaid($appointment);

        return response()->json([
            'message' => 'Deposit marked as paid.',
            'data' => ['appointment' => (new AppointmentResource($appointment->loadMissing(['customer', 'services'])))->resolve()],
        ]);
    }

    public function waiveDeposit(Request $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'payments.waive');
        $this->assertAppointmentAccess($user, $appointment);

        $appointment = $this->policies->waiveDeposit($appointment);

        return response()->json([
            'message' => 'Deposit waived.',
            'data' => ['appointment' => (new AppointmentResource($appointment->loadMissing(['customer', 'services'])))->resolve()],
        ]);
    }

    public function refundDeposit(Request $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'payments.refund');
        $this->assertAppointmentAccess($user, $appointment);

        $appointment = $this->policies->refundDeposit($appointment);

        return response()->json([
            'message' => 'Deposit marked as refunded.',
            'data' => ['appointment' => (new AppointmentResource($appointment->loadMissing(['customer', 'services'])))->resolve()],
        ]);
    }

    public function markNoShowFee(Request $request, Appointment $appointment): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['payments.charge', 'payments.waive']);
        $this->assertAppointmentAccess($user, $appointment);

        $validated = $request->validate([
            'action' => ['sometimes', 'string', Rule::in(['pending', 'charged', 'waived', 'failed'])],
        ]);

        $action = (string) ($validated['action'] ?? 'pending');

        if ($action === 'charged') {
            EnsuresPermission::one($user, 'payments.charge');
        }
        if ($action === 'waived') {
            EnsuresPermission::one($user, 'payments.waive');
        }

        $appointment = $this->policies->markNoShowFee($appointment, $action);

        return response()->json([
            'message' => 'No-show fee updated.',
            'data' => ['appointment' => (new AppointmentResource($appointment->loadMissing(['customer', 'services'])))->resolve()],
        ]);
    }

    private function assertAppointmentAccess(User $user, Appointment $appointment): void
    {
        TenantScope::ensureSaloonAccess($user, (int) $appointment->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $appointment->branch_id !== null ? (int) $appointment->branch_id : null,
            'You can only manage deposits for your current branch.',
        );
    }
}

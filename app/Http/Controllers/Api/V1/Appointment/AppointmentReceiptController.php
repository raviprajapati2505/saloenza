<?php

namespace App\Http\Controllers\Api\V1\Appointment;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Billing\InvoiceReceiptService;
use App\Support\EnsuresPermission;
use App\Support\Branch\BranchScope;
use App\Support\Tenant\TenantScope;
use Symfony\Component\HttpFoundation\Response;

class AppointmentReceiptController extends Controller
{
    public function __construct(
        private readonly InvoiceReceiptService $invoiceReceiptService,
    ) {
    }

    public function __invoke(Appointment $appointment): Response
    {
        /** @var User $user */
        $user = request()->user();
        EnsuresPermission::one($user, 'appointments.view');
        TenantScope::ensureSaloonAccess($user, (int) $appointment->saloon_id);
        BranchScope::ensureSameBranch(
            $user,
            $appointment->branch_id !== null ? (int) $appointment->branch_id : null,
            'You can only download receipts for your current branch.',
        );

        return $this->invoiceReceiptService->pdfResponse($appointment);
    }
}

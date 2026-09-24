<?php

namespace App\Http\Controllers\Api\V1\Payroll;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Payroll\PayRunResource;
use App\Models\PayRun;
use App\Models\User;
use App\Services\Payroll\PayrollService;
use App\Support\Api\ListQuery;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollService $payroll,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'payroll.view');
        $saloonId = TenantScope::resolveSaloonFilter($user, null);

        $validated = ListQuery::validate($request, [
            'status' => ['sometimes', 'string', Rule::in(['draft', 'approved', 'paid'])],
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
        ]);

        $branchId = $this->resolveBranchFilter($user, $request);

        $query = PayRun::query()
            ->with(['branch', 'creator', 'approver'])
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn ($q) => $q->where(function ($inner) use ($branchId): void {
                $inner->whereNull('branch_id')->orWhere('branch_id', $branchId);
            }))
            ->orderByDesc('period_start')
            ->orderByDesc('id');

        if (array_key_exists('status', $validated)) {
            $query->where('status', $validated['status']);
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Pay runs fetched successfully.',
            'pay_runs',
            $paginator,
            PayRunResource::class,
        ));
    }

    public function show(Request $request, PayRun $payRun): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'payroll.view');
        TenantScope::ensureSaloonAccess($user, (int) $payRun->saloon_id);
        $this->ensureBranchAccess($user, $payRun);

        $payRun->load(['lines.user', 'lines.branch', 'branch', 'creator', 'approver']);

        return response()->json([
            'message' => 'Pay run fetched successfully.',
            'data' => ['pay_run' => (new PayRunResource($payRun))->resolve()],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'payroll.manage');

        $validated = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(
                    fn ($query) => $query->where('saloon_id', (int) $user->saloon_id),
                ),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'calculate' => ['sometimes', 'boolean'],
        ]);

        if ($user->isBranchScopedActor() && $user->branch_id) {
            $validated['branch_id'] = (int) $user->branch_id;
        }

        $payRun = $this->payroll->createDraft($user, $validated);

        if (! empty($validated['calculate'])) {
            $payRun = $this->payroll->calculate($payRun);
        } else {
            $payRun->load(['branch', 'creator', 'approver', 'lines.user', 'lines.branch']);
        }

        return response()->json([
            'message' => 'Pay run created successfully.',
            'data' => ['pay_run' => (new PayRunResource($payRun))->resolve()],
        ], 201);
    }

    public function calculate(Request $request, PayRun $payRun): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'payroll.manage');
        TenantScope::ensureSaloonAccess($user, (int) $payRun->saloon_id);
        $this->ensureBranchAccess($user, $payRun);

        $payRun = $this->payroll->calculate($payRun);

        return response()->json([
            'message' => 'Pay run calculated successfully.',
            'data' => ['pay_run' => (new PayRunResource($payRun))->resolve()],
        ]);
    }

    public function approve(Request $request, PayRun $payRun): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'payroll.approve');
        TenantScope::ensureSaloonAccess($user, (int) $payRun->saloon_id);
        $this->ensureBranchAccess($user, $payRun);

        $payRun = $this->payroll->approve($payRun, $user);

        return response()->json([
            'message' => 'Pay run approved successfully.',
            'data' => ['pay_run' => (new PayRunResource($payRun))->resolve()],
        ]);
    }

    public function markPaid(Request $request, PayRun $payRun): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'payroll.manage');
        TenantScope::ensureSaloonAccess($user, (int) $payRun->saloon_id);
        $this->ensureBranchAccess($user, $payRun);

        $payRun = $this->payroll->markPaid($payRun);

        return response()->json([
            'message' => 'Pay run marked as paid.',
            'data' => ['pay_run' => (new PayRunResource($payRun))->resolve()],
        ]);
    }

    public function exportCsv(Request $request, PayRun $payRun): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'payroll.view');
        TenantScope::ensureSaloonAccess($user, (int) $payRun->saloon_id);
        $this->ensureBranchAccess($user, $payRun);

        $rows = $this->payroll->csvRows($payRun);
        $filename = sprintf('pay-run-%d-%s.csv', $payRun->id, $payRun->period_end?->toDateString() ?? 'export');

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function resolveBranchFilter(User $user, Request $request): ?int
    {
        if ($user->isBranchScopedActor() && $user->branch_id) {
            return (int) $user->branch_id;
        }

        return $request->filled('branch_id') ? (int) $request->integer('branch_id') : null;
    }

    private function ensureBranchAccess(User $user, PayRun $payRun): void
    {
        // Salon-wide pay runs (null branch) are visible to all branch-scoped managers.
        if ($payRun->branch_id === null) {
            return;
        }

        BranchScope::ensureSameBranch(
            $user,
            (int) $payRun->branch_id,
            'You can only manage payroll for your current branch.',
        );
    }
}

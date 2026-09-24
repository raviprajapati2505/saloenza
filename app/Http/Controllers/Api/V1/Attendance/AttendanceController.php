<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Attendance\AttendancePunchResource;
use App\Http\Resources\Api\V1\Attendance\LeaveRequestResource;
use App\Models\AttendancePunch;
use App\Models\LeaveRequest;
use App\Models\Shift;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use App\Support\Api\ListQuery;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
    ) {}

    public function punches(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['attendance.view', 'attendance.punch']);
        $saloonId = TenantScope::resolveSaloonFilter($user, null);

        $validated = ListQuery::validate($request, [
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
        ]);

        $branchId = $this->resolveBranchFilter($user, $request);

        $query = AttendancePunch::query()
            ->with(['user', 'branch'])
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('punched_at')
            ->orderByDesc('id');

        // Staff with only punch permission see their own timesheet.
        if (! $user->grantsAllPermissions() && ! $this->canViewAll($user)) {
            $query->where('user_id', $user->id);
        } elseif (array_key_exists('user_id', $validated)) {
            $query->where('user_id', $validated['user_id']);
        }

        if (array_key_exists('from', $validated)) {
            $query->whereDate('punched_at', '>=', $validated['from']);
        }
        if (array_key_exists('to', $validated)) {
            $query->whereDate('punched_at', '<=', $validated['to']);
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Attendance punches fetched successfully.',
            'punches',
            $paginator,
            AttendancePunchResource::class,
        ));
    }

    public function punch(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['attendance.punch', 'attendance.manage']);

        $validated = $request->validate([
            'type' => ['required', 'string', Rule::in(['in', 'out'])],
            'punched_at' => ['nullable', 'date'],
            'method' => ['nullable', 'string', 'max:24'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'geo_lat' => ['nullable', 'numeric'],
            'geo_lng' => ['nullable', 'numeric'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(
                    fn ($query) => $query->where('saloon_id', (int) $user->saloon_id),
                ),
            ],
        ]);

        // Only managers can punch for another user.
        if (isset($validated['user_id']) && (int) $validated['user_id'] !== (int) $user->id) {
            EnsuresPermission::one($user, 'attendance.manage');
        }

        if ($user->isBranchScopedActor() && $user->branch_id) {
            $validated['branch_id'] = (int) $user->branch_id;
        }

        $punch = $this->attendance->punch($user, $validated);
        $punch->load(['user', 'branch']);

        return response()->json([
            'message' => 'Punch recorded successfully.',
            'data' => ['punch' => (new AttendancePunchResource($punch))->resolve()],
        ], 201);
    }

    public function leaves(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['attendance.view', 'attendance.punch', 'leave.approve']);
        $saloonId = TenantScope::resolveSaloonFilter($user, null);

        $validated = ListQuery::validate($request, [
            'status' => ['sometimes', 'string', Rule::in(['pending', 'approved', 'rejected'])],
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
        ]);

        $branchId = $this->resolveBranchFilter($user, $request);

        $query = LeaveRequest::query()
            ->with(['user', 'approver', 'branch'])
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('id');

        if (! $user->grantsAllPermissions() && ! $this->canApproveLeave($user) && ! $this->canViewAll($user)) {
            $query->where('user_id', $user->id);
        } elseif (array_key_exists('user_id', $validated)) {
            $query->where('user_id', $validated['user_id']);
        }

        if (array_key_exists('status', $validated)) {
            $query->where('status', $validated['status']);
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Leave requests fetched successfully.',
            'leave_requests',
            $paginator,
            LeaveRequestResource::class,
        ));
    }

    public function requestLeave(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['attendance.punch', 'attendance.manage', 'attendance.view']);

        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(['annual', 'sick', 'unpaid', 'other'])],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $leave = $this->attendance->requestLeave($user, $validated);
        $leave->load(['user', 'approver']);

        return response()->json([
            'message' => 'Leave request submitted successfully.',
            'data' => ['leave_request' => (new LeaveRequestResource($leave))->resolve()],
        ], 201);
    }

    public function decideLeave(Request $request, LeaveRequest $leaveRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'leave.approve');
        TenantScope::ensureSaloonAccess($user, (int) $leaveRequest->saloon_id);

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['approved', 'rejected'])],
            'decision_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $leave = $this->attendance->decideLeave(
            $leaveRequest,
            $user,
            $validated['status'],
            $validated['decision_notes'] ?? null,
        );

        return response()->json([
            'message' => 'Leave request updated successfully.',
            'data' => ['leave_request' => (new LeaveRequestResource($leave))->resolve()],
        ]);
    }

    public function generateShifts(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'attendance.manage');

        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(
                    fn ($query) => $query->where('saloon_id', (int) $user->saloon_id),
                ),
            ],
        ]);

        $branchId = $user->isBranchScopedActor() && $user->branch_id
            ? (int) $user->branch_id
            : ($validated['branch_id'] ?? null);

        $created = $this->attendance->generateShiftsFromWeekly(
            $user,
            Carbon::parse($validated['from']),
            Carbon::parse($validated['to']),
            $branchId,
        );

        return response()->json([
            'message' => 'Shifts generated from weekly schedules.',
            'data' => [
                'created_count' => $created->count(),
                'shifts' => $created->map(static fn (Shift $shift) => [
                    'id' => $shift->id,
                    'user_id' => $shift->user_id,
                    'starts_at' => $shift->starts_at?->toISOString(),
                    'ends_at' => $shift->ends_at?->toISOString(),
                    'source' => $shift->source,
                    'status' => $shift->status,
                ])->values(),
            ],
        ], 201);
    }

    private function canViewAll(User $user): bool
    {
        return \App\Support\UserPermissions::allows($user, 'attendance.view')
            || \App\Support\UserPermissions::allows($user, 'attendance.manage');
    }

    private function canApproveLeave(User $user): bool
    {
        return \App\Support\UserPermissions::allows($user, 'leave.approve');
    }

    private function resolveBranchFilter(User $user, Request $request): ?int
    {
        if ($user->isBranchScopedActor() && $user->branch_id) {
            return (int) $user->branch_id;
        }

        return $request->filled('branch_id') ? (int) $request->integer('branch_id') : null;
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Branch;

use App\Actions\Branch\ProvisionBranchAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Branch\StoreBranchRequest;
use App\Http\Requests\Api\V1\Branch\UpdateBranchRequest;
use App\Http\Resources\Api\V1\Branch\BranchResource;
use App\Models\SaloonBranch;
use App\Models\User;
use App\Support\Api\ListQuery;
use App\Support\Branch\BranchScope;
use App\Support\EnsuresPermission;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function __construct(
        private readonly SubscriptionEntitlements $entitlements,
        private readonly ProvisionBranchAction $provisioner,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'branches.view');
        BranchScope::ensureAssigned($user);

        if ($user->saloon_id === null) {
            throw new AuthorizationException('Your account is not linked to a saloon.');
        }

        $validated = ListQuery::validate($request, [
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $user->loadMissing('role');
        $isBranchScoped = $user->isBranchScopedActor();

        $query = SaloonBranch::query()
            ->where('saloon_id', $user->saloon_id)
            ->latest('id');

        if ($isBranchScoped) {
            $query->whereKey($user->branch_id);
        } else {
            $query->with(['manager'])
                ->withCount(['users', 'catalogItems']);
        }

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        ListQuery::applySearch($query, $validated['search'] ?? null, ['branch_name', 'city']);

        $paginator = ListQuery::paginate($query, $validated);

        $payload = ListQuery::responsePayload(
            'Branches fetched successfully.',
            'branches',
            $paginator,
            BranchResource::class,
        );

        if ($isBranchScoped) {
            $payload['data']['can_create_branch'] = false;
        } else {
            $snapshot = $this->entitlements->snapshot($user->saloon);
            $payload['data']['limits'] = $snapshot['limits'];
            $payload['data']['can_create_branch'] = $this->canCreateBranch($snapshot['limits']);
        }

        return response()->json($payload);
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('saloon');

        if ($user->saloon === null) {
            throw new AuthorizationException('Your account is not linked to a saloon.');
        }

        $result = $this->provisioner->create($user->saloon, $request->validated());
        $branch = $result['branch']->load(['manager'])->loadCount(['users', 'catalogItems']);

        return response()->json([
            'message' => 'Branch created successfully.',
            'data' => [
                'branch' => (new BranchResource($branch))->resolve(),
                'manager' => $result['manager'],
                'cloned_catalog_items' => $result['cloned_catalog_items'],
                'cloned_roles' => $result['cloned_roles'],
            ],
        ], 201);
    }

    public function update(UpdateBranchRequest $request, SaloonBranch $branch): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'branches.update');
        BranchScope::ensureAssigned($user);

        if ((int) $branch->saloon_id !== (int) $user->saloon_id) {
            throw new AuthorizationException('You cannot manage branches outside your salon.');
        }

        BranchScope::ensureSameBranch(
            $user,
            (int) $branch->id,
            'You can only update your assigned branch.',
        );

        $branch = $this->provisioner->update($branch, $request->validated());

        if (! $user->isBranchScopedActor()) {
            $branch->load(['manager'])->loadCount(['users', 'catalogItems']);
        }

        return response()->json([
            'message' => 'Branch updated successfully.',
            'data' => [
                'branch' => (new BranchResource($branch))->resolve(),
            ],
        ]);
    }

    /**
     * @param array{max_branches: int|null, max_staff: int|null, branches_used: int, staff_used: int} $limits
     */
    private function canCreateBranch(array $limits): bool
    {
        if ($limits['max_branches'] === null) {
            return true;
        }

        return $limits['branches_used'] < $limits['max_branches'];
    }
}

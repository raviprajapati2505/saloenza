<?php

namespace App\Http\Controllers\Api\V1\Saloon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Saloon\SyncSaloonServiceProductsRequest;
use App\Http\Resources\Api\V1\Saloon\SalonServiceProductResource;
use App\Models\SalonServiceProduct;
use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\User;
use App\Support\Branch\BranchScope;
use App\Support\Saloon\SaloonAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaloonServiceProductController extends Controller
{
    public function index(Request $request, Saloon $saloon): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        SaloonAccess::ensureReadable($user, $saloon);
        BranchScope::ensureAssigned($user);

        $query = $saloon->salonServiceProducts()
            ->with(['branch', 'service', 'product'])
            ->orderBy('service_id')
            ->orderBy('branch_id')
            ->orderBy('product_id');

        $user->loadMissing('role');
        if ($user->isBranchScopedActor()) {
            $query->where('branch_id', $user->branch_id);
        }

        $serviceProducts = $query->get();

        return response()->json([
            'message' => 'Saloon service products fetched successfully.',
            'data' => [
                'service_products' => SalonServiceProductResource::collection($serviceProducts)->resolve(),
            ],
        ]);
    }

    public function sync(SyncSaloonServiceProductsRequest $request, Saloon $saloon): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        BranchScope::ensureAssigned($user);
        $user->loadMissing('role');

        if ($user->isBranchScopedActor()) {
            throw new AuthorizationException(
                'Branch managers must update offerings through the catalog API.',
            );
        }

        $payload = $request->validated('service_products', []);
        $this->ensureBranchesBelongToSaloon($saloon, $payload);

        $serviceProducts = DB::transaction(function () use ($payload, $saloon) {
            $saloon->salonServiceProducts()->delete();

            $created = [];

            foreach ($payload as $serviceProductData) {
                $created[] = $saloon->salonServiceProducts()->create([
                    'branch_id' => $serviceProductData['branch_id'] ?? null,
                    'service_id' => (int) $serviceProductData['service_id'],
                    'product_id' => array_key_exists('product_id', $serviceProductData) && $serviceProductData['product_id'] !== null && $serviceProductData['product_id'] !== ''
                        ? (int) $serviceProductData['product_id']
                        : null,
                    'price' => $serviceProductData['price'],
                    'duration_minutes' => (int) $serviceProductData['duration_minutes'],
                    'is_active' => (bool) $serviceProductData['is_active'],
                ]);
            }

            return $created;
        });

        $serviceProducts = SalonServiceProduct::query()
            ->with(['branch', 'service', 'product'])
            ->whereIn('id', collect($serviceProducts)->pluck('id'))
            ->orderBy('service_id')
            ->orderBy('branch_id')
            ->orderBy('product_id')
            ->get();

        return response()->json([
            'message' => 'Saloon service products updated successfully.',
            'data' => [
                'service_products' => SalonServiceProductResource::collection($serviceProducts)->resolve(),
            ],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $payload
     */
    private function ensureBranchesBelongToSaloon(Saloon $saloon, array $payload): void
    {
        $branchIds = collect($payload)
            ->pluck('branch_id')
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($branchIds->isEmpty()) {
            return;
        }

        $validCount = SaloonBranch::query()
            ->where('saloon_id', $saloon->id)
            ->whereIn('id', $branchIds->all())
            ->count();

        if ($validCount !== $branchIds->count()) {
            throw new AuthorizationException('One or more branches do not belong to this saloon.');
        }
    }
}

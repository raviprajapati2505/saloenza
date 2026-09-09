<?php

namespace App\Http\Controllers\Api\V1\Saloon;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Saloon\UpdateSaloonRequest;
use App\Http\Resources\Api\V1\Common\MessageResponseResource;
use App\Http\Resources\Api\V1\Saloon\SaloonResource;
use App\Models\Saloon;
use App\Support\Api\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaloonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = ListQuery::validate($request, [
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $query = Saloon::query()->latest('id');

        if (array_key_exists('is_active', $validated)) {
            $query->where('is_active', (bool) $validated['is_active']);
        }

        ListQuery::applySearch(
            $query,
            $validated['search'] ?? null,
            ['name', 'referral_code', 'transaction_id'],
        );

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Saloons fetched successfully.',
            'saloons',
            $paginator,
            SaloonResource::class,
        ));
    }

    public function show(Saloon $saloon): JsonResponse
    {
        return response()->json([
            'message' => 'Saloon fetched successfully.',
            'data' => [
                'saloon' => (new SaloonResource($saloon))->resolve(),
            ],
        ]);
    }

    public function update(UpdateSaloonRequest $request, Saloon $saloon): JsonResponse
    {
        $saloon->update($this->saloonAttributes($request->validated()));

        return response()->json([
            'message' => 'Saloon updated successfully.',
            'data' => [
                'saloon' => (new SaloonResource($saloon->fresh()))->resolve(),
            ],
        ]);
    }

    public function destroy(Saloon $saloon): JsonResponse
    {
        $saloon->delete();

        return (new MessageResponseResource([
            'message' => 'Saloon deleted successfully.',
        ]))->response();
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function saloonAttributes(array $validated): array
    {
        return [
            'name' => trim((string) $validated['business_name']),
            'payment_type' => $validated['payment_type'],
            'payment_amount' => $validated['payment_amount'],
            'transaction_id' => $validated['transaction_id'] ?? null,
            'is_active' => (bool) $validated['is_active'],
            'referral_code' => isset($validated['referral_code'])
                ? strtoupper(trim((string) $validated['referral_code']))
                : null,
        ];
    }
}

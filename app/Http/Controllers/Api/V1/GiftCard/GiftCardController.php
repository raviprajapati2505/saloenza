<?php

namespace App\Http\Controllers\Api\V1\GiftCard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\GiftCard\GiftCardResource;
use App\Models\GiftCard;
use App\Models\User;
use App\Services\GiftCard\GiftCardService;
use App\Support\Api\ListQuery;
use App\Support\EnsuresPermission;
use App\Support\GiftCard\GiftCardStatus;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GiftCardController extends Controller
{
    public function __construct(
        private readonly GiftCardService $giftCards,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'giftcards.view');
        $saloonId = TenantScope::resolveSaloonFilter($user, null);

        $validated = ListQuery::validate($request, [
            'status' => ['sometimes', 'string', Rule::in(GiftCardStatus::ALL)],
            'customer_id' => ['sometimes', 'integer', 'exists:customers,id'],
        ]);

        $query = GiftCard::query()
            ->with(['purchaser', 'issuer', 'branch'])
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->latest('id');

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['customer_id'])) {
            $query->where('purchaser_customer_id', (int) $validated['customer_id']);
        }

        ListQuery::applySearch($query, $validated['search'] ?? null, ['code', 'recipient_name', 'recipient_phone']);
        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Gift cards fetched successfully.',
            'gift_cards',
            $paginator,
            GiftCardResource::class,
        ));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'giftcards.sell');

        $validated = $request->validate([
            'initial_balance' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'code' => ['nullable', 'string', 'max:40'],
            'currency' => ['nullable', 'string', 'max:10'],
            'purchaser_customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'recipient_name' => ['nullable', 'string', 'max:160'],
            'recipient_phone' => ['nullable', 'string', 'max:40'],
            'expires_at' => ['nullable', 'date'],
            'branch_id' => [
                'nullable',
                'integer',
                Rule::exists('saloon_branches', 'id')->where(
                    fn ($q) => $q->where('saloon_id', (int) $user->saloon_id),
                ),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $giftCard = $this->giftCards->issue($user, $validated);

        return response()->json([
            'message' => 'Gift card issued successfully.',
            'data' => ['gift_card' => (new GiftCardResource($giftCard))->resolve()],
        ], 201);
    }

    public function lookup(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'giftcards.view');

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40'],
        ]);

        $saloonId = (int) ($user->saloon_id ?? 0);
        $giftCard = $this->giftCards->lookup($saloonId, $validated['code']);

        if ($giftCard === null) {
            return response()->json([
                'message' => 'Gift card not found.',
                'data' => ['gift_card' => null],
            ], 404);
        }

        return response()->json([
            'message' => 'Gift card found.',
            'data' => ['gift_card' => (new GiftCardResource($giftCard))->resolve()],
        ]);
    }

    public function show(Request $request, GiftCard $giftCard): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'giftcards.view');
        TenantScope::ensureSaloonAccess($user, (int) $giftCard->saloon_id);

        $giftCard->load([
            'purchaser',
            'issuer',
            'branch',
            'transactions' => fn ($q) => $q->with('creator')->latest('id')->limit(50),
        ]);

        return response()->json([
            'message' => 'Gift card fetched successfully.',
            'data' => ['gift_card' => (new GiftCardResource($giftCard))->resolve()],
        ]);
    }

    public function redeem(Request $request, GiftCard $giftCard): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'giftcards.redeem');
        TenantScope::ensureSaloonAccess($user, (int) $giftCard->saloon_id);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'reason' => ['nullable', 'string', 'max:500'],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
        ]);

        $updated = $this->giftCards->redeem($user, $giftCard, $validated);

        return response()->json([
            'message' => 'Gift card redeemed successfully.',
            'data' => ['gift_card' => (new GiftCardResource($updated))->resolve()],
        ]);
    }

    public function adjust(Request $request, GiftCard $giftCard): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'giftcards.adjust');
        TenantScope::ensureSaloonAccess($user, (int) $giftCard->saloon_id);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'max:99999999'],
            'reason' => ['required', 'string', 'max:500'],
            'status' => ['nullable', 'string', Rule::in(GiftCardStatus::ALL)],
            'appointment_id' => ['nullable', 'integer', 'exists:appointments,id'],
        ]);

        $updated = $this->giftCards->adjust($user, $giftCard, $validated);

        return response()->json([
            'message' => 'Gift card adjusted successfully.',
            'data' => ['gift_card' => (new GiftCardResource($updated))->resolve()],
        ]);
    }
}

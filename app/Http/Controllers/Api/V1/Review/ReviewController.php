<?php

namespace App\Http\Controllers\Api\V1\Review;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Review\ReviewRequestResource;
use App\Http\Resources\Api\V1\Review\ServiceRatingResource;
use App\Models\ReviewRequest;
use App\Models\Saloon;
use App\Models\ServiceRating;
use App\Models\User;
use App\Services\Review\ReviewService;
use App\Support\Api\ListQuery;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function __construct(
        private readonly ReviewService $reviews,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'reviews.view');
        $saloonId = TenantScope::resolveSaloonFilter($user, null);

        $validated = ListQuery::validate($request, [
            'status' => ['sometimes', 'string', 'max:24'],
            'branch_id' => ['sometimes', 'integer', 'exists:saloon_branches,id'],
        ]);

        $branchId = $this->resolveBranchFilter($user, $request);

        $query = ReviewRequest::query()
            ->with(['customer', 'appointment', 'rating', 'creator'])
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('id');

        if (array_key_exists('status', $validated)) {
            $query->where('status', $validated['status']);
        }

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Review requests fetched successfully.',
            'review_requests',
            $paginator,
            ReviewRequestResource::class,
        ));
    }

    public function ratings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'reviews.view');
        $saloonId = TenantScope::resolveSaloonFilter($user, null);
        $validated = ListQuery::validate($request);
        $branchId = $this->resolveBranchFilter($user, $request);

        $query = ServiceRating::query()
            ->with(['customer', 'staff'])
            ->when($saloonId !== null, fn ($q) => $q->where('saloon_id', $saloonId))
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->orderByDesc('id');

        $paginator = ListQuery::paginate($query, $validated);

        return response()->json(ListQuery::responsePayload(
            'Service ratings fetched successfully.',
            'service_ratings',
            $paginator,
            ServiceRatingResource::class,
        ));
    }

    public function schedule(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::any($user, ['reviews.view', 'reviews.manage']);
        $saloonId = TenantScope::resolveSaloonId($user, null);
        $branchId = $this->resolveBranchFilter($user, $request);

        $candidates = $this->reviews->scheduleCandidates($saloonId, $branchId);

        return response()->json([
            'message' => 'Review schedule candidates fetched successfully.',
            'data' => [
                'candidates' => $candidates->map(static fn ($appointment) => [
                    'appointment_id' => $appointment->id,
                    'starts_at' => $appointment->starts_at?->toISOString(),
                    'status' => $appointment->status,
                    'customer' => $appointment->customer ? [
                        'id' => $appointment->customer->id,
                        'name' => $appointment->customer->name,
                        'phone' => $appointment->customer->phone ?? null,
                    ] : null,
                    'staff' => $appointment->staff ? [
                        'id' => $appointment->staff->id,
                        'name' => $appointment->staff->name,
                    ] : null,
                    'branch' => $appointment->branch ? [
                        'id' => $appointment->branch->id,
                        'branch_name' => $appointment->branch->branch_name,
                    ] : null,
                ])->values(),
            ],
        ]);
    }

    public function settings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'reviews.view');
        $salon = $this->resolveSalon($user);

        return response()->json([
            'message' => 'Review settings fetched successfully.',
            'data' => ['settings' => $this->reviews->settingsPayload($salon)],
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'reviews.manage');
        $salon = $this->resolveSalon($user);

        $validated = $request->validate([
            'google_review_url' => ['nullable', 'string', 'max:500'],
        ]);

        if (! empty($validated['google_review_url']) && ! filter_var($validated['google_review_url'], FILTER_VALIDATE_URL)) {
            return response()->json([
                'message' => 'The google review url must be a valid URL.',
                'errors' => ['google_review_url' => ['The google review url must be a valid URL.']],
            ], 422);
        }

        $settings = $this->reviews->updateSettings($salon, $user, $validated);

        return response()->json([
            'message' => 'Review settings updated successfully.',
            'data' => ['settings' => $settings],
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'reviews.manage');
        $salon = $this->resolveSalon($user);

        $validated = $request->validate([
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'staff_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $result = $this->reviews->sendManual($user, $salon, $validated);

        return response()->json([
            'message' => 'Review request sent successfully.',
            'data' => [
                'review_request' => (new ReviewRequestResource($result['request']))->resolve(),
                'rating' => $result['rating']
                    ? (new ServiceRatingResource($result['rating']))->resolve()
                    : null,
                'google_link' => $result['google_link'],
            ],
        ], 201);
    }

    public function rate(Request $request, ReviewRequest $reviewRequest): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'reviews.manage');
        TenantScope::ensureSaloonAccess($user, (int) $reviewRequest->saloon_id);
        $salon = $this->resolveSalon($user);

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'staff_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $result = $this->reviews->recordRating(
            $reviewRequest,
            $salon,
            (int) $validated['rating'],
            $validated['comment'] ?? null,
            isset($validated['staff_user_id']) ? (int) $validated['staff_user_id'] : null,
        );

        return response()->json([
            'message' => 'Rating recorded successfully.',
            'data' => [
                'rating' => (new ServiceRatingResource($result['rating']))->resolve(),
                'review_request' => (new ReviewRequestResource($result['request']))->resolve(),
                'google_link' => $result['google_link'],
            ],
        ]);
    }

    private function resolveSalon(User $user): Saloon
    {
        $saloonId = TenantScope::resolveSaloonId($user, null);

        return Saloon::query()->findOrFail($saloonId);
    }

    private function resolveBranchFilter(User $user, Request $request): ?int
    {
        if ($user->isBranchScopedActor() && $user->branch_id) {
            return (int) $user->branch_id;
        }

        return $request->filled('branch_id') ? (int) $request->integer('branch_id') : null;
    }
}

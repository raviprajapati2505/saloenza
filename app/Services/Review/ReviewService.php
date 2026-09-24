<?php

namespace App\Services\Review;

use App\Models\Appointment;
use App\Models\ReviewRequest;
use App\Models\Saloon;
use App\Models\ServiceRating;
use App\Models\User;
use App\Services\Tenant\TenantSettingsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ReviewService
{
    public const MIN_RATING_FOR_GOOGLE = 4;

    public function __construct(
        private readonly TenantSettingsService $settings,
    ) {}

    public function googleReviewUrl(Saloon $salon): ?string
    {
        $url = $this->settings->resolve($salon, 'reviews', 'google_review_url', '');

        return is_string($url) && trim($url) !== '' ? trim($url) : null;
    }

    /**
     * @param  array{google_review_url?: string|null}  $data
     * @return array{google_review_url: string|null, min_internal_rating_for_public_ask: int}
     */
    public function updateSettings(Saloon $salon, User $actor, array $data): array
    {
        if (array_key_exists('google_review_url', $data)) {
            $this->settings->updateGroup($salon, 'reviews', [
                'google_review_url' => $data['google_review_url'] ?? '',
            ], $actor);
        }

        return $this->settingsPayload($salon);
    }

    /**
     * @return array{google_review_url: string|null, min_internal_rating_for_public_ask: int}
     */
    public function settingsPayload(Saloon $salon): array
    {
        return [
            'google_review_url' => $this->googleReviewUrl($salon),
            'min_internal_rating_for_public_ask' => self::MIN_RATING_FOR_GOOGLE,
        ];
    }

    /**
     * Completed visits without an existing review request (auto-schedule candidates).
     *
     * @return Collection<int, Appointment>
     */
    public function scheduleCandidates(int $saloonId, ?int $branchId = null, int $limit = 50): Collection
    {
        $requestedIds = ReviewRequest::query()
            ->where('saloon_id', $saloonId)
            ->whereNotNull('appointment_id')
            ->pluck('appointment_id');

        return Appointment::query()
            ->with(['customer', 'branch', 'staff'])
            ->where('saloon_id', $saloonId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', 'completed')
            ->whereNotIn('id', $requestedIds)
            ->orderByDesc('starts_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Manual send: create request (+ optional initial rating). Google link only if rating >= 4.
     *
     * @param  array{
     *     appointment_id: int,
     *     rating?: int|null,
     *     comment?: string|null,
     *     staff_user_id?: int|null
     * }  $data
     * @return array{request: ReviewRequest, rating: ServiceRating|null, google_link: string|null}
     */
    public function sendManual(User $actor, Saloon $salon, array $data): array
    {
        $appointment = Appointment::query()
            ->where('saloon_id', $salon->id)
            ->where('id', $data['appointment_id'])
            ->firstOrFail();

        if ($appointment->status !== 'completed') {
            throw ValidationException::withMessages([
                'appointment_id' => 'Review requests can only be sent for completed visits.',
            ]);
        }

        $existing = ReviewRequest::query()
            ->where('appointment_id', $appointment->id)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'appointment_id' => 'A review request already exists for this appointment.',
            ]);
        }

        $ratingValue = isset($data['rating']) ? (int) $data['rating'] : null;
        $googleUrl = $this->googleReviewUrl($salon);
        $eligible = $ratingValue !== null && $ratingValue >= self::MIN_RATING_FOR_GOOGLE;
        $googleLink = $eligible ? $googleUrl : null;

        $request = ReviewRequest::query()->create([
            'saloon_id' => (int) $salon->id,
            'branch_id' => $appointment->branch_id,
            'customer_id' => $appointment->customer_id,
            'appointment_id' => (int) $appointment->id,
            'status' => ReviewRequest::STATUS_SENT,
            'channel' => 'manual',
            'google_link' => $googleLink,
            'google_link_eligible' => $eligible,
            'scheduled_for' => now(),
            'sent_at' => now(),
            'suppress_reason' => $ratingValue !== null && ! $eligible ? 'low_rating' : null,
            'created_by' => (int) $actor->id,
        ]);

        $rating = null;
        if ($ratingValue !== null) {
            $rating = $this->storeRating($request, $appointment, $ratingValue, $data['comment'] ?? null, $data['staff_user_id'] ?? $appointment->staff_id);
        }

        return [
            'request' => $request->fresh(['customer', 'appointment', 'rating', 'creator']),
            'rating' => $rating,
            'google_link' => $googleLink,
        ];
    }

    /**
     * Record / update internal rating and gate Google link.
     *
     * @return array{rating: ServiceRating, request: ReviewRequest, google_link: string|null}
     */
    public function recordRating(ReviewRequest $request, Saloon $salon, int $rating, ?string $comment = null, ?int $staffUserId = null): array
    {
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages([
                'rating' => 'Rating must be between 1 and 5.',
            ]);
        }

        $appointment = $request->appointment;
        $serviceRating = $this->storeRating($request, $appointment, $rating, $comment, $staffUserId ?? $appointment?->staff_id);

        $eligible = $rating >= self::MIN_RATING_FOR_GOOGLE;
        $googleLink = $eligible ? $this->googleReviewUrl($salon) : null;

        $request->update([
            'google_link' => $googleLink,
            'google_link_eligible' => $eligible,
            'suppress_reason' => $eligible ? null : 'low_rating',
            'status' => ReviewRequest::STATUS_SENT,
            'sent_at' => $request->sent_at ?? now(),
        ]);

        return [
            'rating' => $serviceRating->fresh(['staff', 'customer']),
            'request' => $request->fresh(['customer', 'appointment', 'rating']),
            'google_link' => $googleLink,
        ];
    }

    private function storeRating(
        ReviewRequest $request,
        ?Appointment $appointment,
        int $rating,
        ?string $comment,
        mixed $staffUserId,
    ): ServiceRating {
        return ServiceRating::query()->updateOrCreate(
            ['review_request_id' => $request->id],
            [
                'saloon_id' => (int) $request->saloon_id,
                'branch_id' => $request->branch_id,
                'appointment_id' => $request->appointment_id,
                'customer_id' => $request->customer_id,
                'staff_user_id' => $staffUserId !== null ? (int) $staffUserId : null,
                'rating' => $rating,
                'comment' => $comment,
                'shared_publicly' => false,
            ],
        );
    }
}

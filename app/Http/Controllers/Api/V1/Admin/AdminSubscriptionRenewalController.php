<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Saloon;
use App\Models\User;
use App\Support\Role\RoleCodes;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSubscriptionRenewalController extends Controller
{
    public function __construct(
        private readonly SubscriptionEntitlements $entitlements,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:90'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'include_expired' => ['sometimes', 'boolean'],
        ]);

        $withinDays = (int) ($validated['days'] ?? 30);
        $limit = (int) ($validated['limit'] ?? 12);
        $includeExpired = array_key_exists('include_expired', $validated)
            ? (bool) $validated['include_expired']
            : true;

        $rows = Saloon::query()
            ->orderBy('name')
            ->get()
            ->map(function (Saloon $saloon): array {
                $summary = $this->entitlements->salonSubscriptionSummary($saloon);
                $owner = $this->ownerForSaloon($saloon);

                return [
                    'saloon' => [
                        'id' => $saloon->id,
                        'name' => $saloon->name,
                        'city' => $saloon->city,
                        'phone' => $saloon->phone,
                        'is_active' => (bool) $saloon->is_active,
                    ],
                    'owner' => $owner ? [
                        'id' => $owner->id,
                        'name' => $owner->name,
                        'email' => $owner->email,
                        'phone' => $owner->phone,
                    ] : null,
                    'subscription' => $summary,
                ];
            })
            ->filter(function (array $row) use ($withinDays, $includeExpired): bool {
                $summary = $row['subscription'];
                $lifecycle = $summary['lifecycle'];
                $days = $summary['days_remaining'];

                if (in_array($lifecycle, ['expired', 'locked', 'activation_pending'], true)) {
                    return $includeExpired;
                }

                if (in_array($lifecycle, ['expiring_critical', 'expiring_soon', 'expiring_month'], true)) {
                    return true;
                }

                return $days !== null && $days >= 0 && $days <= $withinDays;
            })
            ->sortBy(function (array $row): int {
                $days = $row['subscription']['days_remaining'];

                return $days ?? -999;
            })
            ->values()
            ->take($limit)
            ->all();

        $summary = $this->entitlements->summarizeSaloonSubscriptionLifecycles();

        return response()->json([
            'message' => 'Upcoming subscription renewals fetched successfully.',
            'data' => [
                'renewals' => $rows,
                'summary' => $summary,
                'window_days' => $withinDays,
            ],
        ]);
    }

    private function ownerForSaloon(Saloon $saloon): ?User
    {
        return User::query()
            ->where('saloon_id', $saloon->id)
            ->where('is_active', true)
            ->whereHas('role', function ($query): void {
                $query->whereIn('code', [
                    RoleCodes::SALON_FRANCHISE_OWNER,
                    RoleCodes::LEGACY_SALOON_OWNER,
                ]);
            })
            ->orderBy('id')
            ->first();
    }
}

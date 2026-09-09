<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AffiliatePartner;
use App\Models\AffiliateWithdrawalRequest;
use App\Models\SubscriptionUpgradeOrder;
use App\Support\UserPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class AdminNotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = min(50, max(1, (int) $request->integer('per_page', 20)));

        $notifications = $user->notifications()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (DatabaseNotification $notification) => $this->transform($notification));

        return response()->json([
            'message' => 'Notifications fetched successfully.',
            'data' => [
                'notifications' => $notifications,
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Unread notification count fetched successfully.',
            'data' => [
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function pendingCounts(Request $request): JsonResponse
    {
        $user = $request->user();
        $counts = [
            'affiliate_applications' => 0,
            'affiliate_withdrawals' => 0,
            'subscription_upgrades' => 0,
        ];

        if (UserPermissions::allows($user, 'platform.affiliates.view')) {
            $counts['affiliate_applications'] = AffiliatePartner::query()
                ->where('status', AffiliatePartner::STATUS_PENDING)
                ->count();
            $counts['affiliate_withdrawals'] = AffiliateWithdrawalRequest::query()
                ->where('status', AffiliateWithdrawalRequest::STATUS_PENDING)
                ->count();
        }

        if (UserPermissions::allows($user, 'platform.upgrade_requests.view')) {
            $counts['subscription_upgrades'] = SubscriptionUpgradeOrder::query()
                ->where('status', SubscriptionUpgradeOrder::STATUS_PENDING)
                ->count();
        }

        return response()->json([
            'message' => 'Pending item counts fetched successfully.',
            'data' => [
                'counts' => $counts,
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()
            ->notifications()
            ->where('id', $id)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => [
                'notification' => $this->transform($notification->fresh()),
                'unread_count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'message' => 'All notifications marked as read.',
            'data' => [
                'unread_count' => 0,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];

        return [
            'id' => $notification->id,
            'event' => $data['event'] ?? $notification->type,
            'title' => $data['title'] ?? 'Notification',
            'body' => $data['body'] ?? '',
            'action_url' => $data['action_url'] ?? null,
            'meta' => $data['meta'] ?? [],
            'read_at' => $notification->read_at?->toISOString(),
            'created_at' => $notification->created_at?->toISOString(),
        ];
    }
}

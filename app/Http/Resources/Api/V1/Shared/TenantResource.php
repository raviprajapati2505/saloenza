<?php

namespace App\Http\Resources\Api\V1\Shared;

use App\Models\Saloon;
use App\Support\Subscription\SubscriptionEntitlements;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Saloon */
class TenantResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SubscriptionEntitlements $entitlements */
        $entitlements = app(SubscriptionEntitlements::class);
        $summary = $entitlements->salonSubscriptionSummary($this->resource);
        $snapshot = $entitlements->snapshot($this->resource);
        $plan = $summary['plan'] ?? ($snapshot['plan'] ? [
            'id' => $snapshot['plan']->id,
            'name' => $snapshot['plan']->name,
            'slug' => $snapshot['plan']->slug,
            'price' => (float) $snapshot['plan']->price,
            'billing_interval' => $snapshot['plan']->billing_interval,
        ] : null);
        $activationPending = $this->resource->isActivationPending();
        $pendingOrder = $activationPending
            ? app(\App\Services\Subscription\SubscriptionUpgradeService::class)
                ->pendingOrderForSaloon($this->resource)
            : null;
        $subscriptionRow = $snapshot['subscription'];
        /** @var \App\Services\Tenant\TenantSettingsService $tenantSettings */
        $tenantSettings = app(\App\Services\Tenant\TenantSettingsService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => (bool) $this->is_active,
            'activation_status' => $this->activation_status ?: Saloon::ACTIVATION_ACTIVE,
            'activation_pending' => $activationPending,
            'subscription_access_mode' => $summary['access_mode'],
            'subscription_expired' => in_array($summary['access_mode'], [
                SubscriptionEntitlements::ACCESS_LOCKED,
                SubscriptionEntitlements::ACCESS_READ_ONLY,
            ], true),
            'requested_plan' => $pendingOrder?->toPlan ? [
                'id' => $pendingOrder->toPlan->id,
                'name' => $pendingOrder->toPlan->name,
                'slug' => $pendingOrder->toPlan->slug,
                'price' => (float) $pendingOrder->toPlan->price,
            ] : null,
            'plan' => $plan,
            'subscription' => $subscriptionRow ? [
                'id' => $subscriptionRow->id,
                'status' => $subscriptionRow->status,
                'starts_at' => $subscriptionRow->starts_at?->toISOString(),
                'ends_at' => $subscriptionRow->ends_at?->toISOString(),
                'trial_ends_at' => $subscriptionRow->trial_ends_at?->toISOString(),
            ] : ($summary['subscription'] ?? null),
            'subscription_summary' => [
                'lifecycle' => $summary['lifecycle'],
                'lifecycle_label' => $summary['lifecycle_label'],
                'days_remaining' => $summary['days_remaining'],
                'renewal_due_at' => $summary['renewal_due_at'],
            ],
            'modules' => $snapshot['modules'],
            'limits' => $snapshot['limits'],
            'trial_ends_at' => $snapshot['trial_ends_at'],
            'branding' => $tenantSettings->brandingPayload($this->resource),
            'regional' => $tenantSettings->regionalPayload($this->resource),
        ];
    }
}

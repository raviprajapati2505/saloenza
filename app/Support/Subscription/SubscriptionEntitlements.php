<?php

namespace App\Support\Subscription;

use App\Models\Saloon;
use App\Models\SaloonBranch;
use App\Models\SaloonSubscription;
use App\Models\SaloonSubscriptionHistory;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\Role\RoleCodes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SubscriptionEntitlements
{
    public const ACCESS_FULL = 'full';

    public const ACCESS_LOCKED = 'locked';

    public const ACCESS_READ_ONLY = 'read_only';

    public function activeSubscription(Saloon $saloon): ?SaloonSubscription
    {
        return SaloonSubscription::query()
            ->with('plan')
            ->where('saloon_id', $saloon->id)
            ->whereIn('status', [SaloonSubscription::STATUS_ACTIVE, SaloonSubscription::STATUS_TRIALING])
            ->orderByDesc('id')
            ->get()
            ->first(fn (SaloonSubscription $subscription) => $subscription->isCurrentlyActive());
    }

    public function latestSubscription(Saloon $saloon): ?SaloonSubscription
    {
        return SaloonSubscription::query()
            ->with('plan')
            ->where('saloon_id', $saloon->id)
            ->whereIn('status', [
                SaloonSubscription::STATUS_ACTIVE,
                SaloonSubscription::STATUS_TRIALING,
                SaloonSubscription::STATUS_EXPIRED,
            ])
            ->orderByDesc('id')
            ->first();
    }

    public function accessMode(Saloon $saloon): string
    {
        if ($saloon->isActivationPending()) {
            return self::ACCESS_LOCKED;
        }

        if ($this->activeSubscription($saloon) !== null) {
            return self::ACCESS_FULL;
        }

        $latest = $this->latestSubscription($saloon);
        if ($latest === null) {
            return self::ACCESS_FULL;
        }

        if ($latest->status === SaloonSubscription::STATUS_EXPIRED) {
            $plan = $latest->plan;

            return ($plan !== null && $plan->isFreePlan())
                ? self::ACCESS_LOCKED
                : self::ACCESS_READ_ONLY;
        }

        if ($latest->isCurrentlyActive()) {
            return self::ACCESS_FULL;
        }

        $plan = $latest->plan;
        if ($plan !== null && $plan->isFreePlan()) {
            return self::ACCESS_LOCKED;
        }

        return self::ACCESS_READ_ONLY;
    }

    public function isSubscriptionLocked(Saloon $saloon): bool
    {
        return $this->accessMode($saloon) === self::ACCESS_LOCKED;
    }

    public function isSubscriptionReadOnly(Saloon $saloon): bool
    {
        return $this->accessMode($saloon) === self::ACCESS_READ_ONLY;
    }

    public function activePlan(Saloon $saloon): ?SubscriptionPlan
    {
        return $this->activeSubscription($saloon)?->plan;
    }

    public function effectivePlan(Saloon $saloon): ?SubscriptionPlan
    {
        $active = $this->activeSubscription($saloon);
        if ($active !== null) {
            return $active->plan;
        }

        $accessMode = $this->accessMode($saloon);
        if (in_array($accessMode, [self::ACCESS_READ_ONLY, self::ACCESS_LOCKED], true)) {
            return $this->latestSubscription($saloon)?->plan;
        }

        return null;
    }

    /**
     * @return array{
     *     access_mode: string,
     *     lifecycle: string,
     *     lifecycle_label: string,
     *     days_remaining: int|null,
     *     renewal_due_at: string|null,
     *     plan: array{id: int, name: string, slug: string, price: float, billing_interval: string|null, is_free: bool}|null,
     *     subscription: array{id: int, status: string, starts_at: string|null, ends_at: string|null, trial_ends_at: string|null}|null,
     *     activation_pending: bool
     * }
     */
    public function salonSubscriptionSummary(Saloon $saloon): array
    {
        $accessMode = $this->accessMode($saloon);
        $activationPending = $saloon->isActivationPending();
        $subscription = $this->activeSubscription($saloon) ?? $this->latestSubscription($saloon);
        $plan = $subscription?->plan ?? $this->effectivePlan($saloon);
        $dueAt = $subscription?->renewalDueAt();

        $daysRemaining = null;
        if ($dueAt !== null) {
            $daysRemaining = (int) round(now()->startOfDay()->diffInDays($dueAt->copy()->startOfDay(), false));
        }

        $lifecycle = $this->resolveLifecycle($accessMode, $activationPending, $daysRemaining);
        $lifecycleLabels = [
            'activation_pending' => 'Activation pending',
            'locked' => 'Locked',
            'expired' => 'Expired',
            'expiring_critical' => 'Expiring ≤ 7 days',
            'expiring_soon' => 'Expiring ≤ 15 days',
            'expiring_month' => 'Expiring ≤ 30 days',
            'active' => 'Active',
        ];

        return [
            'access_mode' => $accessMode,
            'lifecycle' => $lifecycle,
            'lifecycle_label' => $lifecycleLabels[$lifecycle] ?? 'Active',
            'days_remaining' => $daysRemaining,
            'renewal_due_at' => $dueAt?->toISOString(),
            'plan' => $plan ? [
                'id' => $plan->id,
                'name' => $plan->name,
                'slug' => $plan->slug,
                'price' => (float) $plan->price,
                'billing_interval' => $plan->billing_interval,
                'is_free' => $plan->isFreePlan(),
            ] : null,
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'starts_at' => $subscription->starts_at?->toISOString(),
                'ends_at' => $subscription->ends_at?->toISOString(),
                'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
            ] : null,
            'activation_pending' => $activationPending,
        ];
    }

    /**
     * @return list<int>
     */
    public function saloonIdsMatchingLifecycle(string $lifecycle): array
    {
        return Saloon::query()
            ->orderBy('id')
            ->get()
            ->filter(fn (Saloon $saloon) => $this->salonSubscriptionSummary($saloon)['lifecycle'] === $lifecycle)
            ->pluck('id')
            ->all();
    }

    /**
     * @param list<int>|null $saloonIds
     * @return array{active: int, expiring_month: int, expiring_soon: int, expiring_critical: int, expiring_total: int, expired: int, locked: int, activation_pending: int, total: int}
     */
    public function summarizeSaloonSubscriptionLifecycles(?array $saloonIds = null): array
    {
        $query = Saloon::query()->orderBy('id');
        if ($saloonIds !== null) {
            $query->whereIn('id', $saloonIds);
        }

        $counts = [
            'active' => 0,
            'expiring_month' => 0,
            'expiring_soon' => 0,
            'expiring_critical' => 0,
            'expired' => 0,
            'locked' => 0,
            'activation_pending' => 0,
            'total' => 0,
        ];

        foreach ($query->get() as $saloon) {
            $lifecycle = $this->salonSubscriptionSummary($saloon)['lifecycle'];
            if (isset($counts[$lifecycle])) {
                $counts[$lifecycle]++;
            }
            $counts['total']++;
        }

        $counts['expiring_total'] = $counts['expiring_month'] + $counts['expiring_soon'] + $counts['expiring_critical'];

        return $counts;
    }

    private function resolveLifecycle(string $accessMode, bool $activationPending, ?int $daysRemaining): string
    {
        if ($activationPending) {
            return 'activation_pending';
        }

        if ($accessMode === self::ACCESS_LOCKED) {
            return 'locked';
        }

        if ($accessMode === self::ACCESS_READ_ONLY) {
            return 'expired';
        }

        if ($daysRemaining !== null && $daysRemaining <= 7) {
            return 'expiring_critical';
        }

        if ($daysRemaining !== null && $daysRemaining <= 15) {
            return 'expiring_soon';
        }

        if ($daysRemaining !== null && $daysRemaining <= 30) {
            return 'expiring_month';
        }

        return 'active';
    }

    /**
     * @return array{
     *     plan: SubscriptionPlan|null,
     *     subscription: SaloonSubscription|null,
     *     modules: list<string>,
     *     limits: array{max_branches: int|null, max_staff: int|null, branches_used: int, staff_used: int},
     *     trial_ends_at: string|null
     * }
     */
    public function snapshot(Saloon $saloon): array
    {
        $accessMode = $this->accessMode($saloon);
        $subscription = $this->activeSubscription($saloon)
            ?? ($accessMode !== self::ACCESS_FULL ? $this->latestSubscription($saloon) : null);
        $plan = $this->effectivePlan($saloon);

        return [
            'plan' => $plan,
            'subscription' => $subscription,
            'modules' => $accessMode === self::ACCESS_LOCKED ? [] : ($plan?->moduleList() ?? []),
            'limits' => [
                'max_branches' => $plan?->max_branches,
                'max_staff' => $plan?->max_staff,
                'branches_used' => $this->countBranches($saloon),
                'staff_used' => $this->countStaff($saloon),
            ],
            'trial_ends_at' => $subscription?->trial_ends_at?->toISOString(),
            'access_mode' => $accessMode,
        ];
    }

    public function assignPlan(
        Saloon $saloon,
        SubscriptionPlan $plan,
        bool $startTrial = false,
        ?int $trialDays = null,
        ?int $changedByUserId = null,
        ?int $upgradeOrderId = null,
        ?string $notes = null,
    ): SaloonSubscription {
        return DB::transaction(function () use (
            $saloon,
            $plan,
            $startTrial,
            $trialDays,
            $changedByUserId,
            $upgradeOrderId,
            $notes,
        ): SaloonSubscription {
            $previousPlanId = $this->activePlan($saloon)?->id;

            $activeRows = SaloonSubscription::query()
                ->where('saloon_id', $saloon->id)
                ->whereIn('status', [SaloonSubscription::STATUS_ACTIVE, SaloonSubscription::STATUS_TRIALING])
                ->get();

            foreach ($activeRows as $existing) {
                $cancelPayload = [
                    'status' => SaloonSubscription::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'ends_at' => now(),
                ];
                if ($this->hasReminderSentAtColumn()) {
                    $cancelPayload['renewal_reminder_sent_at'] = null;
                }
                if ($this->hasReminderDaysSentColumn()) {
                    $cancelPayload['renewal_reminder_days_sent'] = null;
                }

                $existing->update($cancelPayload);

                $this->recordHistory(
                    saloon: $saloon,
                    subscription: $existing->fresh(),
                    planId: (int) $existing->subscription_plan_id,
                    fromPlanId: null,
                    action: SaloonSubscriptionHistory::ACTION_CANCELLED,
                    changedByUserId: $changedByUserId,
                    upgradeOrderId: $upgradeOrderId,
                    notes: $notes ?? 'Replaced by a new subscription assignment.',
                );
            }

            $startsAt = now();
            $trialEndsAt = null;
            $status = SaloonSubscription::STATUS_ACTIVE;
            $resolvedTrialDays = $trialDays !== null ? max(0, $trialDays) : (int) $plan->trial_days;
            $isTrialing = $startTrial && $resolvedTrialDays > 0;

            if ($isTrialing) {
                $status = SaloonSubscription::STATUS_TRIALING;
                $trialEndsAt = $startsAt->copy()->addDays($resolvedTrialDays);
            }

            $endsAt = $this->resolvePeriodEndsAt($startsAt, $plan, $trialEndsAt, $isTrialing);

            $createPayload = [
                'saloon_id' => $saloon->id,
                'subscription_plan_id' => $plan->id,
                'status' => $status,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'trial_ends_at' => $trialEndsAt,
            ];
            if ($this->hasReminderSentAtColumn()) {
                $createPayload['renewal_reminder_sent_at'] = null;
            }
            if ($this->hasReminderDaysSentColumn()) {
                $createPayload['renewal_reminder_days_sent'] = null;
            }

            $subscription = SaloonSubscription::query()->create($createPayload);

            $this->recordHistory(
                saloon: $saloon,
                subscription: $subscription,
                planId: (int) $plan->id,
                fromPlanId: $previousPlanId,
                action: $isTrialing
                    ? SaloonSubscriptionHistory::ACTION_TRIAL_STARTED
                    : SaloonSubscriptionHistory::ACTION_ASSIGNED,
                changedByUserId: $changedByUserId,
                upgradeOrderId: $upgradeOrderId,
                notes: $notes,
            );

            return $subscription;
        });
    }

    public function recordHistory(
        Saloon $saloon,
        SaloonSubscription $subscription,
        int $planId,
        ?int $fromPlanId,
        string $action,
        ?int $changedByUserId = null,
        ?int $upgradeOrderId = null,
        ?string $notes = null,
        ?array $meta = null,
    ): ?SaloonSubscriptionHistory {
        if (! $this->hasSubscriptionHistoryTable()) {
            return null;
        }

        return SaloonSubscriptionHistory::query()->create([
            'saloon_id' => $saloon->id,
            'saloon_subscription_id' => $subscription->id,
            'subscription_plan_id' => $planId,
            'from_subscription_plan_id' => $fromPlanId,
            'subscription_upgrade_order_id' => $upgradeOrderId,
            'changed_by_user_id' => $changedByUserId,
            'action' => $action,
            'status' => $subscription->status,
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
            'trial_ends_at' => $subscription->trial_ends_at,
            'cancelled_at' => $subscription->cancelled_at,
            'notes' => $notes,
            'meta' => $meta,
        ]);
    }

    public function resolvePeriodEndsAt(
        Carbon $startsAt,
        SubscriptionPlan $plan,
        ?Carbon $trialEndsAt,
        bool $isTrialing,
    ): ?Carbon {
        if ($isTrialing) {
            return $trialEndsAt;
        }

        return match ($plan->billing_interval) {
            'quarterly' => $startsAt->copy()->addMonths(3),
            'yearly' => $startsAt->copy()->addYear(),
            'trial' => $trialEndsAt ?? $startsAt->copy()->addDays(max(1, (int) $plan->trial_days)),
            default => $startsAt->copy()->addMonth(),
        };
    }

    public function hasModule(Saloon $saloon, string $module): bool
    {
        if ($this->accessMode($saloon) === self::ACCESS_LOCKED) {
            return false;
        }

        $plan = $this->effectivePlan($saloon);

        return $plan !== null && $plan->hasModule($module);
    }

    public function ensureModule(Saloon $saloon, string $module): void
    {
        if ($this->hasModule($saloon, $module)) {
            return;
        }

        $planName = $this->activePlan($saloon)?->name ?? 'your plan';

        throw new HttpException(403, "The {$module} module is not included in {$planName}. Please upgrade your subscription.");
    }

    public function ensureCanAddBranch(Saloon $saloon): void
    {
        $plan = $this->activePlan($saloon);

        if ($plan === null) {
            throw new HttpException(403, 'No active subscription found for this salon.');
        }

        if ($plan->hasUnlimitedBranches()) {
            return;
        }

        $used = $this->countBranches($saloon);

        if ($used >= (int) $plan->max_branches) {
            throw new HttpException(403, "Branch limit reached ({$plan->max_branches}). Upgrade your subscription to add more branches.");
        }
    }

    public function ensureCanAddStaff(Saloon $saloon): void
    {
        $plan = $this->activePlan($saloon);

        if ($plan === null) {
            throw new HttpException(403, 'No active subscription found for this salon.');
        }

        if ($plan->hasUnlimitedStaff()) {
            return;
        }

        $used = $this->countStaff($saloon);

        if ($used >= (int) $plan->max_staff) {
            throw new HttpException(403, "Staff limit reached ({$plan->max_staff}). Upgrade your subscription to add more staff.");
        }
    }

    public function countBranches(Saloon $saloon): int
    {
        return SaloonBranch::query()->where('saloon_id', $saloon->id)->count();
    }

    public function countStaff(Saloon $saloon): int
    {
        return User::query()
            ->where('saloon_id', $saloon->id)
            ->where('is_system_admin', false)
            ->whereHas('role', fn ($query) => $query->where('code', '!=', RoleCodes::SALON_FRANCHISE_OWNER))
            ->count();
    }

    public function findPublicPlanBySlug(string $slug): ?SubscriptionPlan
    {
        return SubscriptionPlan::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->where('is_public', true)
            ->first();
    }

    public function findPublicPlanById(int $id): ?SubscriptionPlan
    {
        return SubscriptionPlan::query()
            ->whereKey($id)
            ->where('is_active', true)
            ->where('is_public', true)
            ->first();
    }

    /**
     * @return array{
     *     allowed: bool,
     *     is_current: bool,
     *     is_downgrade: bool,
     *     blockers: list<array{code: string, used: int, limit: int, message: string}>,
     *     message: string|null
     * }
     */
    public function evaluatePlanSwitch(Saloon $saloon, SubscriptionPlan $target, ?SubscriptionPlan $current = null): array
    {
        $current ??= $this->activePlan($saloon);
        $branchesUsed = $this->countBranches($saloon);
        $staffUsed = $this->countStaff($saloon);
        $blockers = [];

        if ($current !== null && $current->id === $target->id) {
            return [
                'allowed' => false,
                'is_current' => true,
                'is_downgrade' => false,
                'blockers' => [],
                'message' => 'This is your current subscription plan.',
            ];
        }

        if (! $target->hasUnlimitedBranches() && $branchesUsed > (int) $target->max_branches) {
            $limit = (int) $target->max_branches;
            $blockers[] = [
                'code' => 'branches',
                'used' => $branchesUsed,
                'limit' => $limit,
                'message' => "You have {$branchesUsed} active branches but {$target->name} allows only {$limit}.",
            ];
        }

        if (! $target->hasUnlimitedStaff() && $staffUsed > (int) $target->max_staff) {
            $limit = (int) $target->max_staff;
            $blockers[] = [
                'code' => 'staff',
                'used' => $staffUsed,
                'limit' => $limit,
                'message' => "You have {$staffUsed} active staff but {$target->name} allows only {$limit}.",
            ];
        }

        $isDowngrade = $this->isDowngrade($current, $target);
        $allowed = $blockers === [];

        $message = null;
        if (! $allowed) {
            $details = implode(' ', array_column($blockers, 'message'));
            $message = $isDowngrade
                ? "Cannot downgrade to {$target->name}. {$details} Remove extra branches or staff before downgrading."
                : "Cannot switch to {$target->name}. {$details}";
        }

        return [
            'allowed' => $allowed,
            'is_current' => false,
            'is_downgrade' => $isDowngrade,
            'blockers' => $blockers,
            'message' => $message,
        ];
    }

    public function ensureCanSwitchToPlan(Saloon $saloon, SubscriptionPlan $target): void
    {
        $evaluation = $this->evaluatePlanSwitch($saloon, $target);

        if ($evaluation['is_current']) {
            throw new HttpException(422, $evaluation['message'] ?? 'Your salon is already on this subscription plan.');
        }

        if (! $evaluation['allowed']) {
            throw new HttpException(422, $evaluation['message'] ?? 'This plan change is not allowed with your current usage.');
        }
    }

    private function isDowngrade(?SubscriptionPlan $current, SubscriptionPlan $target): bool
    {
        if ($current === null) {
            return false;
        }

        if ((int) $target->sort_order !== (int) $current->sort_order) {
            return (int) $target->sort_order < (int) $current->sort_order;
        }

        return (float) $target->price < (float) $current->price;
    }

    private function hasReminderSentAtColumn(): bool
    {
        static $exists = null;

        if ($exists === null) {
            $exists = Schema::hasColumn('saloon_subscriptions', 'renewal_reminder_sent_at');
        }

        return $exists;
    }

    private function hasReminderDaysSentColumn(): bool
    {
        static $exists = null;

        if ($exists === null) {
            $exists = Schema::hasColumn('saloon_subscriptions', 'renewal_reminder_days_sent');
        }

        return $exists;
    }

    private function hasSubscriptionHistoryTable(): bool
    {
        static $exists = null;

        if ($exists === null) {
            $exists = Schema::hasTable('saloon_subscription_histories');
        }

        return $exists;
    }
}

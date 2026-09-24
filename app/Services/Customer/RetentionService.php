<?php

namespace App\Services\Customer;

use App\Mail\RetentionWinbackMail;
use App\Models\Appointment;
use App\Models\CustomerValueStat;
use App\Models\RetentionCohort;
use App\Models\RetentionCohortMember;
use App\Models\RetentionPolicy;
use App\Models\Saloon;
use App\Models\User;
use App\Services\Tenant\TenantNotificationDispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetentionService
{
    public function __construct(
        private readonly CustomerClvService $clvService,
        private readonly TenantNotificationDispatcher $notifications,
    ) {
    }

    public function resolveOrCreateDefaultPolicy(int $saloonId): RetentionPolicy
    {
        $policy = RetentionPolicy::query()
            ->where('saloon_id', $saloonId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($policy) {
            return $policy;
        }

        return RetentionPolicy::query()->create([
            'saloon_id' => $saloonId,
            'name' => 'Default retention',
            'at_risk_after_days' => 40,
            'lapsed_after_days' => 60,
            'lost_after_days' => 120,
            'min_visits_required' => 1,
            'exclude_tag_ids' => [],
            'channels_allowed' => ['email', 'manual'],
            'is_active' => true,
        ]);
    }

    /**
     * @return array{classified: int, counts: array<string, int>}
     */
    public function classifyCustomers(int $saloonId, ?RetentionPolicy $policy = null): array
    {
        $policy ??= $this->resolveOrCreateDefaultPolicy($saloonId);

        // Ensure visit metrics exist before lapse classification.
        $this->clvService->recomputeForSalon($saloonId);

        $excludeTagIds = array_map('intval', $policy->exclude_tag_ids ?? []);
        $now = now();
        $counts = ['active' => 0, 'at_risk' => 0, 'lapsed' => 0, 'lost' => 0];
        $classified = 0;

        $futureBookedIds = Appointment::query()
            ->where('saloon_id', $saloonId)
            ->whereNotNull('customer_id')
            ->where('starts_at', '>', $now)
            ->whereIn('status', ['scheduled', 'confirmed', 'in-progress'])
            ->pluck('customer_id')
            ->unique()
            ->all();

        $futureSet = array_fill_keys(array_map('intval', $futureBookedIds), true);

        CustomerValueStat::query()
            ->where('saloon_id', $saloonId)
            ->with(['customer.tags'])
            ->orderBy('id')
            ->chunkById(200, function (Collection $chunk) use (
                $policy,
                $excludeTagIds,
                $futureSet,
                $now,
                &$counts,
                &$classified,
            ): void {
                foreach ($chunk as $stat) {
                    /** @var CustomerValueStat $stat */
                    $customer = $stat->customer;
                    if (! $customer || ! $customer->is_active) {
                        continue;
                    }

                    if ($excludeTagIds !== [] && $customer->relationLoaded('tags')) {
                        $tagIds = $customer->tags->pluck('id')->map(fn ($id) => (int) $id)->all();
                        if (array_intersect($excludeTagIds, $tagIds) !== []) {
                            continue;
                        }
                    }

                    if ((int) $stat->visit_count < (int) $policy->min_visits_required) {
                        continue;
                    }

                    $status = 'active';
                    if (isset($futureSet[(int) $stat->customer_id])) {
                        $status = 'active';
                    } elseif ($stat->last_visit_at === null) {
                        $status = 'lost';
                    } else {
                        $days = (int) $stat->last_visit_at->diffInDays($now);
                        if ($days >= (int) $policy->lost_after_days) {
                            $status = 'lost';
                        } elseif ($days >= (int) $policy->lapsed_after_days) {
                            $status = 'lapsed';
                        } elseif ($days >= (int) $policy->at_risk_after_days) {
                            $status = 'at_risk';
                        }
                    }

                    if ((string) $stat->lapse_status !== $status) {
                        $stat->forceFill([
                            'lapse_status' => $status,
                            'lapse_status_updated_at' => $now,
                        ])->save();
                    } elseif ($stat->lapse_status_updated_at === null) {
                        $stat->forceFill(['lapse_status_updated_at' => $now])->save();
                    }

                    $counts[$status] = ($counts[$status] ?? 0) + 1;
                    $classified++;
                }
            });

        return [
            'classified' => $classified,
            'counts' => $counts,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByStatus(int $saloonId, string $status, ?int $branchId = null, int $limit = 200): array
    {
        if (! in_array($status, CustomerValueStat::LAPSE_STATUSES, true) || $status === 'active') {
            // Allow at_risk|lapsed|lost primarily; active still works.
        }

        $query = CustomerValueStat::query()
            ->where('saloon_id', $saloonId)
            ->where('lapse_status', $status)
            ->with(['customer:id,name,email,phone,is_active'])
            ->orderByDesc('lifetime_spend')
            ->orderByDesc('clv_score');

        if ($branchId !== null) {
            $query->whereHas('customer.appointments', function ($inner) use ($saloonId, $branchId): void {
                $inner->where('saloon_id', $saloonId)->where('branch_id', $branchId);
            });
        }

        return $query->limit($limit)->get()->map(function (CustomerValueStat $stat): array {
            $daysSince = $stat->last_visit_at
                ? (int) $stat->last_visit_at->diffInDays(now())
                : null;

            return [
                'customer_id' => (int) $stat->customer_id,
                'customer' => $stat->customer ? [
                    'id' => (int) $stat->customer->id,
                    'name' => $stat->customer->name,
                    'email' => $stat->customer->email,
                    'phone' => $stat->customer->phone,
                    'is_active' => (bool) $stat->customer->is_active,
                ] : null,
                'lapse_status' => (string) $stat->lapse_status,
                'clv_tier' => (string) $stat->clv_tier,
                'clv_score' => (int) $stat->clv_score,
                'lifetime_spend' => (float) $stat->lifetime_spend,
                'visit_count' => (int) $stat->visit_count,
                'avg_ticket' => (float) $stat->avg_ticket,
                'last_visit_at' => $stat->last_visit_at?->toISOString(),
                'days_since_visit' => $daysSince,
                'churn_risk_score' => $stat->churn_risk_score !== null ? (int) $stat->churn_risk_score : null,
                'priority_score' => round(
                    ((float) $stat->lifetime_spend * 0.5)
                    + ((float) $stat->avg_ticket * 0.3)
                    + ((int) $stat->visit_count * 0.2),
                    2,
                ),
            ];
        })->all();
    }

    /**
     * @param array{
     *     name: string,
     *     status?: string,
     *     policy_id?: int|null,
     *     branch_id?: int|null,
     *     lapse_status?: string,
     *     customer_ids?: list<int>|null,
     *     template_channel?: string,
     *     note?: string|null
     * } $payload
     */
    public function createCohort(int $saloonId, User $actor, array $payload): RetentionCohort
    {
        $lapseStatus = (string) ($payload['lapse_status'] ?? 'at_risk');
        if (! in_array($lapseStatus, ['at_risk', 'lapsed', 'lost'], true)) {
            throw ValidationException::withMessages([
                'lapse_status' => ['Cohort segment must be at_risk, lapsed, or lost.'],
            ]);
        }

        $policyId = $payload['policy_id'] ?? $this->resolveOrCreateDefaultPolicy($saloonId)->id;
        $channel = (string) ($payload['template_channel'] ?? 'manual');
        if (! in_array($channel, ['email', 'sms', 'manual'], true)) {
            $channel = 'manual';
        }

        $membersSource = $this->listByStatus($saloonId, $lapseStatus, $payload['branch_id'] ?? null);
        $requestedIds = isset($payload['customer_ids']) && is_array($payload['customer_ids'])
            ? array_map('intval', $payload['customer_ids'])
            : null;

        if ($requestedIds !== null) {
            $allowed = array_fill_keys($requestedIds, true);
            $membersSource = array_values(array_filter(
                $membersSource,
                fn (array $row): bool => isset($allowed[(int) $row['customer_id']]),
            ));
        }

        return DB::transaction(function () use ($saloonId, $actor, $payload, $policyId, $channel, $lapseStatus, $membersSource, $requestedIds): RetentionCohort {
            $cohort = RetentionCohort::query()->create([
                'saloon_id' => $saloonId,
                'branch_id' => $payload['branch_id'] ?? null,
                'policy_id' => $policyId,
                'name' => trim((string) $payload['name']),
                'status' => (string) ($payload['status'] ?? 'ready'),
                'segment_filter_json' => [
                    'lapse_status' => $lapseStatus,
                    'customer_ids' => $requestedIds,
                ],
                'template_channel' => $channel,
                'template_code' => $payload['template_code'] ?? null,
                'note' => $payload['note'] ?? null,
                'created_by' => (int) $actor->id,
                'stats_json' => [
                    'targeted' => count($membersSource),
                    'sent' => 0,
                    'failed' => 0,
                    'skipped' => 0,
                    'manual' => 0,
                ],
            ]);

            foreach ($membersSource as $row) {
                RetentionCohortMember::query()->create([
                    'cohort_id' => $cohort->id,
                    'customer_id' => (int) $row['customer_id'],
                    'lapse_status_at_add' => $lapseStatus,
                    'priority_score' => (float) $row['priority_score'],
                    'message_status' => 'pending',
                ]);
            }

            return $cohort->load(['members.customer:id,name,email,phone', 'policy:id,name']);
        });
    }

    /**
     * Mark members as sent (manual) and optionally attempt email delivery.
     *
     * @param list<int>|null $memberIds
     * @return array{sent: int, manual: int, failed: int, skipped: int}
     */
    public function markMembersSent(RetentionCohort $cohort, ?array $memberIds = null, bool $tryDeliver = false): array
    {
        $salon = Saloon::query()->findOrFail((int) $cohort->saloon_id);
        $query = $cohort->members()->with('customer:id,name,email,phone')->where('message_status', 'pending');

        if ($memberIds !== null && $memberIds !== []) {
            $query->whereIn('id', array_map('intval', $memberIds));
        }

        $sent = 0;
        $manual = 0;
        $failed = 0;
        $skipped = 0;
        $channel = (string) $cohort->template_channel;
        $now = now();

        foreach ($query->get() as $member) {
            /** @var RetentionCohortMember $member */
            $customer = $member->customer;
            if (! $customer || ! $customer->is_active) {
                $member->forceFill([
                    'message_status' => 'skipped',
                    'skip_reason' => 'inactive_or_missing',
                ])->save();
                $skipped++;

                continue;
            }

            if ($tryDeliver && $channel === 'email') {
                $email = trim((string) ($customer->email ?? ''));
                if ($email === '') {
                    $member->forceFill([
                        'message_status' => 'skipped',
                        'skip_reason' => 'missing_email',
                    ])->save();
                    $skipped++;

                    continue;
                }

                try {
                    $delivered = $this->notifications->sendMail(
                        $salon,
                        $email,
                        new RetentionWinbackMail($salon, $customer, (string) ($cohort->note ?? '')),
                        null,
                        true,
                    );

                    if ($delivered) {
                        $member->forceFill([
                            'message_status' => 'sent',
                            'sent_at' => $now,
                        ])->save();
                        $sent++;
                    } else {
                        $member->forceFill([
                            'message_status' => 'failed',
                            'skip_reason' => 'mail_disabled_or_failed',
                        ])->save();
                        $failed++;
                    }
                } catch (\Throwable $e) {
                    $member->forceFill([
                        'message_status' => 'failed',
                        'skip_reason' => 'mail_exception',
                    ])->save();
                    $failed++;
                }

                continue;
            }

            // P0 default: mark as manual (export / human outreach).
            $member->forceFill([
                'message_status' => 'manual',
                'sent_at' => $now,
            ])->save();
            $manual++;
        }

        $stats = $cohort->stats_json ?? [];
        $stats['sent'] = (int) ($stats['sent'] ?? 0) + $sent;
        $stats['manual'] = (int) ($stats['manual'] ?? 0) + $manual;
        $stats['failed'] = (int) ($stats['failed'] ?? 0) + $failed;
        $stats['skipped'] = (int) ($stats['skipped'] ?? 0) + $skipped;

        $pendingLeft = $cohort->members()->where('message_status', 'pending')->exists();
        $cohort->forceFill([
            'stats_json' => $stats,
            'status' => $pendingLeft ? 'sending' : 'completed',
        ])->save();

        return compact('sent', 'manual', 'failed', 'skipped');
    }

    /**
     * @return array<string, mixed>
     */
    public function cohortPayload(RetentionCohort $cohort): array
    {
        $cohort->loadMissing(['members.customer:id,name,email,phone', 'policy:id,name', 'creator:id,name']);

        return [
            'id' => (int) $cohort->id,
            'saloon_id' => (int) $cohort->saloon_id,
            'branch_id' => $cohort->branch_id !== null ? (int) $cohort->branch_id : null,
            'policy_id' => $cohort->policy_id !== null ? (int) $cohort->policy_id : null,
            'policy' => $cohort->policy ? [
                'id' => (int) $cohort->policy->id,
                'name' => $cohort->policy->name,
            ] : null,
            'name' => $cohort->name,
            'status' => $cohort->status,
            'segment_filter_json' => $cohort->segment_filter_json,
            'template_channel' => $cohort->template_channel,
            'template_code' => $cohort->template_code,
            'note' => $cohort->note,
            'scheduled_at' => $cohort->scheduled_at?->toISOString(),
            'created_by' => $cohort->created_by !== null ? (int) $cohort->created_by : null,
            'creator' => $cohort->creator ? [
                'id' => (int) $cohort->creator->id,
                'name' => $cohort->creator->name,
            ] : null,
            'stats_json' => $cohort->stats_json,
            'members' => $cohort->members->map(fn (RetentionCohortMember $m) => [
                'id' => (int) $m->id,
                'customer_id' => (int) $m->customer_id,
                'customer' => $m->customer ? [
                    'id' => (int) $m->customer->id,
                    'name' => $m->customer->name,
                    'email' => $m->customer->email,
                    'phone' => $m->customer->phone,
                ] : null,
                'lapse_status_at_add' => $m->lapse_status_at_add,
                'priority_score' => (float) $m->priority_score,
                'message_status' => $m->message_status,
                'sent_at' => $m->sent_at?->toISOString(),
                'skip_reason' => $m->skip_reason,
            ])->values()->all(),
            'created_at' => $cohort->created_at?->toISOString(),
            'updated_at' => $cohort->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function policyPayload(RetentionPolicy $policy): array
    {
        return [
            'id' => (int) $policy->id,
            'saloon_id' => (int) $policy->saloon_id,
            'name' => $policy->name,
            'at_risk_after_days' => (int) $policy->at_risk_after_days,
            'lapsed_after_days' => (int) $policy->lapsed_after_days,
            'lost_after_days' => (int) $policy->lost_after_days,
            'min_visits_required' => (int) $policy->min_visits_required,
            'exclude_tag_ids' => $policy->exclude_tag_ids ?? [],
            'channels_allowed' => $policy->channels_allowed ?? [],
            'is_active' => (bool) $policy->is_active,
            'created_at' => $policy->created_at?->toISOString(),
            'updated_at' => $policy->updated_at?->toISOString(),
        ];
    }
}

<?php

namespace App\Services\Marketing;

use App\Mail\MarketingCampaignMail;
use App\Models\Customer;
use App\Models\CustomerSegment;
use App\Models\MarketingCampaign;
use App\Models\MarketingCampaignRecipient;
use App\Models\Saloon;
use App\Services\Tenant\TenantNotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MarketingCampaignService
{
    public function __construct(
        private readonly MarketingSegmentService $segments,
        private readonly TenantNotificationDispatcher $notifications,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(int $saloonId, int $userId, array $data): MarketingCampaign
    {
        $channel = (string) ($data['channel'] ?? MarketingCampaign::CHANNEL_EMAIL);
        if (! in_array($channel, [MarketingCampaign::CHANNEL_EMAIL, MarketingCampaign::CHANNEL_SMS], true)) {
            throw ValidationException::withMessages(['channel' => 'Only email and sms channels are supported.']);
        }

        $segmentId = isset($data['segment_id']) ? (int) $data['segment_id'] : null;
        if ($segmentId) {
            $exists = CustomerSegment::query()
                ->forSaloon($saloonId)
                ->whereKey($segmentId)
                ->exists();
            if (! $exists) {
                throw ValidationException::withMessages(['segment_id' => 'Segment not found for this salon.']);
            }
        }

        return MarketingCampaign::query()->create([
            'saloon_id' => $saloonId,
            'segment_id' => $segmentId,
            'name' => trim((string) $data['name']),
            'channel' => $channel,
            'subject' => $channel === MarketingCampaign::CHANNEL_EMAIL
                ? trim((string) ($data['subject'] ?? $data['name'] ?? 'Message from your salon'))
                : ($data['subject'] ?? null),
            'body' => trim((string) $data['body']),
            'status' => MarketingCampaign::STATUS_DRAFT,
            'created_by' => $userId,
            'stats_json' => [
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'pending' => 0,
            ],
        ]);
    }

    public function send(MarketingCampaign $campaign): MarketingCampaign
    {
        if ($campaign->status === MarketingCampaign::STATUS_SENT && (int) (($campaign->stats_json['pending'] ?? 0)) === 0) {
            throw ValidationException::withMessages(['campaign' => 'Campaign was already sent.']);
        }

        if (! $campaign->segment_id) {
            throw ValidationException::withMessages(['segment_id' => 'Campaign requires a segment.']);
        }

        /** @var CustomerSegment $segment */
        $segment = $campaign->segment()->firstOrFail();
        $customers = $this->segments->resolveCustomers($segment);
        /** @var Saloon $salon */
        $salon = Saloon::query()->findOrFail((int) $campaign->saloon_id);

        $campaign->status = MarketingCampaign::STATUS_SENDING;
        $campaign->save();

        $stats = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'pending' => 0];

        DB::transaction(function () use ($campaign, $customers, $salon, &$stats): void {
            MarketingCampaignRecipient::query()->where('campaign_id', $campaign->id)->delete();

            foreach ($customers as $customer) {
                $recipient = MarketingCampaignRecipient::query()->create([
                    'campaign_id' => $campaign->id,
                    'customer_id' => $customer->id,
                    'channel' => $campaign->channel,
                    'status' => MarketingCampaignRecipient::STATUS_PENDING,
                ]);

                $this->deliver($campaign, $salon, $customer, $recipient, $stats);
            }
        });

        $campaign->stats_json = $stats;
        if ($stats['pending'] > 0 && $stats['sent'] === 0) {
            $campaign->status = MarketingCampaign::STATUS_SENDING;
        } elseif ($stats['sent'] === 0 && $stats['failed'] > 0 && $stats['pending'] === 0) {
            $campaign->status = MarketingCampaign::STATUS_FAILED;
        } else {
            $campaign->status = MarketingCampaign::STATUS_SENT;
            $campaign->sent_at = Carbon::now();
        }
        $campaign->save();

        return $campaign->fresh(['segment', 'recipients.customer']);
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function deliver(
        MarketingCampaign $campaign,
        Saloon $salon,
        Customer $customer,
        MarketingCampaignRecipient $recipient,
        array &$stats,
    ): void {
        if ($campaign->channel === MarketingCampaign::CHANNEL_EMAIL) {
            $email = trim((string) ($customer->email ?? ''));
            if ($email === '') {
                $recipient->update([
                    'status' => MarketingCampaignRecipient::STATUS_SKIPPED,
                    'error' => 'Missing email address.',
                ]);
                $stats['skipped']++;

                return;
            }

            try {
                $ok = $this->notifications->sendMail(
                    $salon,
                    $email,
                    new MarketingCampaignMail(
                        $salon,
                        $customer,
                        (string) ($campaign->subject ?: $campaign->name),
                        (string) $campaign->body,
                    ),
                    'marketing_campaign',
                    true,
                );

                if ($ok) {
                    $recipient->update([
                        'status' => MarketingCampaignRecipient::STATUS_SENT,
                        'sent_at' => Carbon::now(),
                        'error' => null,
                    ]);
                    $stats['sent']++;
                } else {
                    $recipient->update([
                        'status' => MarketingCampaignRecipient::STATUS_FAILED,
                        'error' => 'Email delivery disabled or failed.',
                    ]);
                    $stats['failed']++;
                }
            } catch (\Throwable $e) {
                $recipient->update([
                    'status' => MarketingCampaignRecipient::STATUS_FAILED,
                    'error' => $e->getMessage(),
                ]);
                $stats['failed']++;
            }

            return;
        }

        // SMS left pending/manual — stub always returns false.
        $phone = trim((string) ($customer->phone ?? ''));
        if ($phone === '') {
            $recipient->update([
                'status' => MarketingCampaignRecipient::STATUS_SKIPPED,
                'error' => 'Missing phone number.',
            ]);
            $stats['skipped']++;

            return;
        }

        $ok = $this->sendSms($salon, $phone, (string) $campaign->body);
        if ($ok) {
            $recipient->update([
                'status' => MarketingCampaignRecipient::STATUS_SENT,
                'sent_at' => Carbon::now(),
            ]);
            $stats['sent']++;
        } else {
            $recipient->update([
                'status' => MarketingCampaignRecipient::STATUS_PENDING,
                'error' => 'SMS delivery is not configured; marked for manual send.',
            ]);
            $stats['pending']++;
        }
    }

    /**
     * SMS channel stub — returns false until a provider is wired.
     */
    public function sendSms(Saloon $salon, string $phone, string $message): bool
    {
        unset($salon, $phone, $message);

        return false;
    }
}

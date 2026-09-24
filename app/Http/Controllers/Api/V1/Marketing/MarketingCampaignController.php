<?php

namespace App\Http\Controllers\Api\V1\Marketing;

use App\Http\Controllers\Controller;
use App\Models\MarketingCampaign;
use App\Models\User;
use App\Services\Marketing\MarketingCampaignService;
use App\Support\EnsuresPermission;
use App\Support\Tenant\TenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketingCampaignController extends Controller
{
    public function __construct(
        private readonly MarketingCampaignService $campaigns,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'marketing.view');

        $saloonId = (int) TenantScope::resolveSaloonFilter($user, null);

        $rows = MarketingCampaign::query()
            ->forSaloon($saloonId)
            ->with('segment')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'message' => 'Marketing campaigns fetched successfully.',
            'data' => [
                'campaigns' => $rows->map(fn (MarketingCampaign $campaign) => $this->payload($campaign))->all(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'marketing.manage');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'segment_id' => ['required', 'integer', 'exists:customer_segments,id'],
            'channel' => ['required', Rule::in([MarketingCampaign::CHANNEL_EMAIL, MarketingCampaign::CHANNEL_SMS])],
            'subject' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $saloonId = (int) TenantScope::resolveSaloonFilter($user, null);
        $campaign = $this->campaigns->create($saloonId, (int) $user->id, $validated);

        return response()->json([
            'message' => 'Marketing campaign created successfully.',
            'data' => ['campaign' => $this->payload($campaign->load('segment'))],
        ], 201);
    }

    public function show(Request $request, MarketingCampaign $campaign): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'marketing.view');
        $this->ensureCampaignAccess($user, $campaign);

        $campaign->load(['segment', 'recipients.customer']);

        return response()->json([
            'message' => 'Marketing campaign fetched successfully.',
            'data' => [
                'campaign' => $this->payload($campaign, true),
            ],
        ]);
    }

    public function send(Request $request, MarketingCampaign $campaign): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        EnsuresPermission::one($user, 'marketing.send');
        $this->ensureCampaignAccess($user, $campaign);

        $campaign = $this->campaigns->send($campaign);

        return response()->json([
            'message' => 'Marketing campaign sent successfully.',
            'data' => ['campaign' => $this->payload($campaign, true)],
        ]);
    }

    private function ensureCampaignAccess(User $user, MarketingCampaign $campaign): void
    {
        $saloonId = TenantScope::resolveSaloonFilter($user, null);
        if ($saloonId !== null && (int) $campaign->saloon_id !== (int) $saloonId) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(MarketingCampaign $campaign, bool $withRecipients = false): array
    {
        $data = [
            'id' => $campaign->id,
            'saloon_id' => $campaign->saloon_id,
            'segment_id' => $campaign->segment_id,
            'name' => $campaign->name,
            'channel' => $campaign->channel,
            'subject' => $campaign->subject,
            'body' => $campaign->body,
            'status' => $campaign->status,
            'sent_at' => $campaign->sent_at?->toISOString(),
            'stats' => $campaign->stats_json,
            'segment' => $campaign->relationLoaded('segment') && $campaign->segment ? [
                'id' => $campaign->segment->id,
                'name' => $campaign->segment->name,
                'type' => $campaign->segment->type,
                'estimated_size' => (int) $campaign->segment->estimated_size,
            ] : null,
            'created_at' => $campaign->created_at?->toISOString(),
        ];

        if ($withRecipients && $campaign->relationLoaded('recipients')) {
            $data['recipients'] = $campaign->recipients->map(fn ($recipient) => [
                'id' => $recipient->id,
                'customer_id' => $recipient->customer_id,
                'channel' => $recipient->channel,
                'status' => $recipient->status,
                'sent_at' => $recipient->sent_at?->toISOString(),
                'error' => $recipient->error,
                'customer' => $recipient->customer ? [
                    'id' => $recipient->customer->id,
                    'name' => $recipient->customer->name,
                    'email' => $recipient->customer->email,
                    'phone' => $recipient->customer->phone,
                ] : null,
            ])->all();
        }

        return $data;
    }
}

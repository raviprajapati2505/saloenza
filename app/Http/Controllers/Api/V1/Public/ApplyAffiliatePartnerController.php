<?php

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Affiliate\ApplyAffiliatePartnerAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Public\ApplyAffiliatePartnerRequest;
use App\Http\Resources\Api\V1\Affiliate\AffiliatePartnerResource;
use App\Services\Notifications\PlatformAdminNotifier;
use Illuminate\Http\JsonResponse;

class ApplyAffiliatePartnerController extends Controller
{
    public function __construct(
        private readonly ApplyAffiliatePartnerAction $applyAffiliatePartnerAction,
        private readonly PlatformAdminNotifier $platformAdminNotifier,
    ) {
    }

    public function __invoke(ApplyAffiliatePartnerRequest $request): JsonResponse
    {
        $partner = $this->applyAffiliatePartnerAction->execute($request->validated());
        $partner->load('user');

        $displayName = $partner->display_name ?: $partner->user?->name ?: 'An applicant';

        $this->platformAdminNotifier->notify(
            event: 'affiliate.partner.applied',
            title: 'New affiliate application',
            body: "{$displayName} requested to join as an affiliate partner.",
            actionUrl: '/admin/affiliates?tab=partners&status=pending',
            requiredPermissions: ['platform.affiliates.view'],
            meta: [
                'affiliate_partner_id' => $partner->id,
                'code' => $partner->code,
            ],
        );

        return response()->json([
            'message' => 'Affiliate application submitted successfully. An admin will review your request.',
            'data' => [
                'affiliate_partner' => (new AffiliatePartnerResource($partner))->resolve(),
            ],
        ], 201);
    }
}

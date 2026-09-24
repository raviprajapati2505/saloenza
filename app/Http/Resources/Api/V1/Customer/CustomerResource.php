<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Customer;
use App\Support\Customer\CustomerContactAccess;
use App\Support\Customer\CustomerContactPayload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Customer */
class CustomerResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $visitStats = null;
        if ($this->relationLoaded('appointments')) {
            $appointments = $this->appointments;
            $visitStats = [
                'total_visits' => (int) ($this->appointments_count ?? $appointments->count()),
                'total_spent' => round((float) $appointments->sum(fn ($visit) => (float) ($visit->grand_total ?? 0)), 2),
                'last_visit_at' => $appointments->max('starts_at')?->toISOString(),
            ];
        }

        $viewer = $request->user();
        $canViewContact = CustomerContactAccess::canView($viewer, $this->resource);
        $identity = CustomerContactPayload::identity($this->resource, $viewer);

        return [
            'id' => $this->id,
            'saloon_ids' => $this->when(
                $this->relationLoaded('saloons'),
                fn () => $this->saloons->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ),
            'saloons' => $this->when(
                $this->relationLoaded('saloons'),
                fn () => $this->saloons->map(fn ($saloon) => [
                    'id' => (int) $saloon->id,
                    'name' => $saloon->name,
                ])->values()->all(),
            ),
            'name' => $this->name,
            'email' => $identity['email'],
            'phone' => $identity['phone'],
            'whatsapp' => $identity['whatsapp'],
            'can_view_contact' => $canViewContact,
            'has_phone' => $identity['has_phone'],
            'has_whatsapp' => $identity['has_whatsapp'],
            'has_email' => $identity['has_email'],
            'birthday' => $this->birthday?->toDateString(),
            'anniversary' => $this->anniversary?->toDateString(),
            'notes' => $this->notes,
            'is_active' => (bool) $this->is_active,
            'tags' => $this->when(
                $this->relationLoaded('tags'),
                fn () => $this->tags->map(fn ($tag) => [
                    'id' => (int) $tag->id,
                    'saloon_id' => (int) $tag->saloon_id,
                    'name' => $tag->name,
                    'color' => $tag->color,
                ])->values()->all(),
            ),
            'appointments_count' => $this->when(
                $this->appointments_count !== null,
                fn () => (int) $this->appointments_count,
            ),
            'clv' => $this->when(
                $this->relationLoaded('valueStats') && $this->valueStats->isNotEmpty(),
                function () {
                    $stat = $this->valueStats->first();

                    return [
                        'clv_score' => (int) $stat->clv_score,
                        'clv_tier' => (string) $stat->clv_tier,
                        'lifetime_spend' => (float) $stat->lifetime_spend,
                        'visit_count' => (int) $stat->visit_count,
                        'lapse_status' => (string) $stat->lapse_status,
                        'churn_risk_score' => $stat->churn_risk_score !== null
                            ? (int) $stat->churn_risk_score
                            : null,
                    ];
                },
            ),
            'visit_stats' => $this->when($visitStats !== null, $visitStats),
            'recent_visits' => $this->when(
                $this->relationLoaded('appointments'),
                fn () => CustomerVisitResource::collection($this->appointments)->resolve(),
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

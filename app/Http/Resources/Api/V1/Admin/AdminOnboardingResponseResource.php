<?php

namespace App\Http\Resources\Api\V1\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOnboardingResponseResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'saloon' => (new AdminSaloonResource($this->resource['saloon']))->resolve(),
            'branch' => $this->resource['branch']
                ? (new SaloonBranchResource($this->resource['branch']))->resolve()
                : null,
            'user' => $this->resource['user']
                ? (new OnboardingStaffResource($this->resource['user']))->resolve()
                : null,
            'service_products' => SalonServiceProductResource::collection($this->resource['service_products'])->resolve(),
        ];
    }
}

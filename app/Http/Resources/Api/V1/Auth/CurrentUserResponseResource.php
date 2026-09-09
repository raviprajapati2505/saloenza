<?php

namespace App\Http\Resources\Api\V1\Auth;

use App\Http\Resources\Api\V1\Shared\AuthenticatedContextResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrentUserResponseResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => (new AuthenticatedContextResource([
                'user' => $this->resource,
            ]))->resolve($request),
        ];
    }
}

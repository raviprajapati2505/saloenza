<?php

namespace App\Http\Resources\Api\V1\Profile;

use App\Http\Resources\Api\V1\Shared\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResponseResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => 'Profile updated successfully.',
            'data' => [
                'user' => new UserResource($this->resource),
            ],
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1\Auth;

use App\Http\Resources\Api\V1\Shared\RegisterUserResource;
use App\Http\Resources\Api\V1\Shared\SaloonResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegisterResponseResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => 'Registration completed successfully.',
            'data' => [
                'user' => new RegisterUserResource($this->resource['user']),
                'saloon' => new SaloonResource($this->resource['saloon']),
            ],
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1\Shared;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class RegisterUserResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'saloon_id' => $this->saloon_id,
        ];
    }
}

<?php

namespace App\Http\Resources\Api\V1\Shared;

use App\Models\Saloon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Saloon */
class SaloonResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, int|string>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}

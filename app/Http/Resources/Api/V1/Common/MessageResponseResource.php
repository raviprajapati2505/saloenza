<?php

namespace App\Http\Resources\Api\V1\Common;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResponseResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, string>
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => (string) $this->resource['message'],
        ];
    }
}

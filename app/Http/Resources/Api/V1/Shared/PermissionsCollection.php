<?php

namespace App\Http\Resources\Api\V1\Shared;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class PermissionsCollection extends ResourceCollection
{
    public static $wrap = null;

    /**
     * @return list<string>
     */
    public function toArray(Request $request): array
    {
        return $this->collection
            ->map(fn (mixed $permission): string => (string) $permission)
            ->values()
            ->all();
    }
}

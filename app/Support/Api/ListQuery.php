<?php

namespace App\Support\Api;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ListQuery
{
    public const MAX_PER_PAGE = 100;

    /**
     * @param array<string, mixed> $extraRules
     * @return array<string, mixed>
     */
    public static function validate(Request $request, array $extraRules = []): array
    {
        $request->merge([
            'page' => $request->input('page', 1),
            'per_page' => $request->input('per_page', 50),
        ]);

        return $request->validate(array_merge([
            'search' => ['sometimes', 'nullable', 'string', 'max:120'],
            'page' => ['required', 'integer', 'min:1'],
            'per_page' => ['required', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ], $extraRules));
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $query
     * @param array<int, string> $columns
     */
    public static function applySearch(Builder $query, ?string $search, array $columns = ['name']): void
    {
        if ($search === null || trim($search) === '') {
            return;
        }

        $search = trim($search);

        $query->where(function (Builder $builder) use ($search, $columns): void {
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $builder->where($column, 'like', "%{$search}%");
                } else {
                    $builder->orWhere($column, 'like', "%{$search}%");
                }
            }
        });
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $query
     * @param array<string, mixed> $validated
     */
    public static function paginate(Builder $query, array $validated): LengthAwarePaginator
    {
        return $query->paginate(
            (int) $validated['per_page'],
            ['*'],
            'page',
            (int) $validated['page'],
        );
    }

    /**
     * @param class-string<JsonResource> $resourceClass
     * @return array<string, mixed>
     */
    public static function responsePayload(
        string $message,
        string $collectionKey,
        LengthAwarePaginator $paginator,
        string $resourceClass,
    ): array {
        return [
            'message' => $message,
            'data' => [
                $collectionKey => $resourceClass::collection($paginator->items())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ];
    }
}

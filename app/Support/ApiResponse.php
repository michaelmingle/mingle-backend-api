<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

/**
 * Every JSON response in this API is shaped
 *   { "success": bool, "data": mixed|null, "message": string|null }
 * with validation failures adding an "errors" object. This helper is the single
 * place that shape is defined.
 */
class ApiResponse
{
    public static function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => self::normalize($data),
            'message' => $message,
        ], $status);
    }

    public static function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return self::success($data, $message, 201);
    }

    public static function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'data' => null,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }

    /**
     * Paginated payloads keep the records under `data.items` and expose the page
     * cursor under `data.meta`, so clients never have to special-case them.
     */
    public static function paginated(
        AbstractPaginator|LengthAwarePaginator|ResourceCollection $paginator,
        ?string $message = null,
    ): JsonResponse {
        $resolved = $paginator instanceof ResourceCollection
            ? $paginator->resource
            : $paginator;

        $items = $paginator instanceof ResourceCollection
            ? $paginator->collection
            : $resolved->getCollection();

        return self::success([
            'items' => self::normalize($items),
            'meta' => [
                'current_page' => $resolved->currentPage(),
                'per_page' => $resolved->perPage(),
                'total' => method_exists($resolved, 'total') ? $resolved->total() : null,
                'last_page' => method_exists($resolved, 'lastPage') ? $resolved->lastPage() : null,
                'has_more' => $resolved->hasMorePages(),
            ],
        ], $message);
    }

    private static function normalize(mixed $data): mixed
    {
        if ($data === null) {
            return null;
        }

        // resolve() rather than toArray(): it is what strips nested
        // `whenLoaded()` placeholders. Calling toArray() directly leaves
        // MissingValue objects in the payload and blows up on encode.
        if ($data instanceof JsonResource) {
            return $data->resolve(request());
        }

        if (is_object($data) && method_exists($data, 'toArray') && ! is_array($data)) {
            return $data->toArray(request());
        }

        return $data;
    }
}

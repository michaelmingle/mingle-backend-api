<?php

namespace App\Support\Concerns;

use App\Support\ApiResponse;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\AbstractPaginator;

/** Thin sugar so controllers read as `$this->ok(...)` rather than the FQCN. */
trait InteractsWithApiResponses
{
    protected function ok(mixed $data = null, ?string $message = null): JsonResponse
    {
        return ApiResponse::success($data, $message);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return ApiResponse::created($data, $message);
    }

    protected function fail(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return ApiResponse::error($message, $status, $errors);
    }

    protected function paginated(
        AbstractPaginator|LengthAwarePaginator|ResourceCollection $paginator,
        ?string $message = null,
    ): JsonResponse {
        return ApiResponse::paginated($paginator, $message);
    }

    /** Clamped per-page value taken from the request. */
    protected function perPage(?int $default = null): int
    {
        $default ??= (int) config('mingle.pagination.default', 20);
        $requested = (int) request()->integer('per_page', $default);

        return (int) min(
            (int) config('mingle.pagination.max', 100),
            max(1, $requested ?: $default)
        );
    }
}

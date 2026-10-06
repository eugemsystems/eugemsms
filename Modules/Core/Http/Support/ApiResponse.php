<?php

declare(strict_types=1);

namespace Modules\Core\Http\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Volume 1 §9 — the `/api/v1` response envelope: `success`, `data`, and `meta` with a
 * request id and UTC timestamp. Money in `data` is always produced by `Money`'s own
 * `jsonSerialize()` (`amount_minor`, `currency`, `formatted`), never a bare number.
 */
final class ApiResponse
{
    /**
     * @param  array<mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    public static function ok(array $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data, 'meta' => self::meta($meta)], $status);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     */
    public static function page(array $items, LengthAwarePaginator $paginator): JsonResponse
    {
        return self::ok($items, [
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function meta(array $extra = []): array
    {
        return $extra + [
            'request_id' => 'req_'.Str::ulid(),
            'timestamp' => Carbon::now('UTC')->toIso8601ZuluString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => ['code' => $code, 'message' => $message, 'details' => (object) $details],
            'meta' => self::meta(),
        ], $status);
    }

    public static function perPage(?int $requested): int
    {
        return min(100, max(1, $requested ?? 25));
    }
}

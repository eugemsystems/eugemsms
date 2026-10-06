<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Core\Http\Support\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Volume 1 §9.1 — an `Idempotency-Key` header is required on every payment and financial
 * mutation. The first completed response for a key is stored for 24 hours and replayed
 * to a retry of the same request (a flaky connection double-submits); the same key with
 * a different body is refused as `IDEMPOTENCY_CONFLICT` rather than silently doing
 * something else. Server errors are not stored, so a retry after a 5xx runs again.
 */
final class RequireIdempotencyKey
{
    private const int TTL_SECONDS = 86400;

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $key = trim((string) $request->header('Idempotency-Key'));

        if ($key === '' || strlen($key) > 120) {
            return ApiResponse::error('IDEMPOTENCY_KEY_REQUIRED', 'This request needs an Idempotency-Key header (up to 120 characters).', 400);
        }

        $cacheKey = 'idempotency:'.($request->user()?->getAuthIdentifier() ?? 'guest').':'.sha1($request->method().'|'.$request->path().'|'.$key);
        $fingerprint = sha1((string) json_encode($request->all()));
        $stored = Cache::get($cacheKey);

        if (is_array($stored)) {
            if ($stored['fingerprint'] !== $fingerprint) {
                return ApiResponse::error('IDEMPOTENCY_CONFLICT', 'This Idempotency-Key was already used with a different request.', 409);
            }

            return response($stored['body'], $stored['status'], ['Content-Type' => 'application/json', 'Idempotent-Replay' => 'true']);
        }

        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            Cache::put($cacheKey, ['fingerprint' => $fingerprint, 'status' => $response->getStatusCode(), 'body' => (string) $response->getContent()], self::TTL_SECONDS);
        }

        return $response;
    }
}

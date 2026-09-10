<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Book A Part 1.10, step 8. Last-seen tracking and request-ID injection
 * for audit correlation. Full activity/audit logging belongs to CORE-08.
 */
final class RecordActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->user()?->forceFill(['last_seen_at' => now()])->saveQuietly();

        $requestId = $request->header('X-Request-Id') ?: (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}

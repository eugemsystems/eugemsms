<?php

declare(strict_types=1);

namespace Modules\Intelligence\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Domain\Exceptions\SerpException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Intelligence\Domain\Events\RateLimitExceeded;
use Modules\Intelligence\Models\ApiClient;
use Modules\Intelligence\Models\ApiUsageLog;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Book J INT-04 §3 ⭐/BR-INT-04-001/002/007. Authenticates a third-party or
 * device credential, which is an `api_clients` key and never a Sanctum user token.
 * The bearer is `{client ulid}.{secret}`; the ulid finds the row and the full key is
 * verified against `api_key_hash`. A revoked or inactive client, a caller outside
 * the client's IP allowlist and a client missing a route's declared abilities
 * (`serp.api-client:attendance:write`) are each refused here, on every request, whatever
 * else is configured (AC-INT-04-001), then enforces the client's own rate limit (BR-INT-04-003) and logs the call to
 * `api_usage_log` whatever its outcome. On success the client's school becomes the ambient
 * `SchoolContext` and the client is exposed as the `api_client` request attribute.
 */
final class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next, string ...$abilities): Response
    {
        $client = $this->resolveClient((string) $request->bearerToken());

        if ($client === null) {
            return ApiResponse::error('UNAUTHENTICATED', 'A valid API key is required.', 401);
        }

        if ($client->ip_allowlist !== null && $client->ip_allowlist !== [] && ! IpUtils::checkIp((string) $request->ip(), $client->ip_allowlist)) {
            return ApiResponse::error('IP_NOT_ALLOWED', 'This API key may not be used from this address.', 403);
        }

        SchoolContext::set($client->school);
        $request->attributes->set('api_client', $client);

        $startedAt = hrtime(true);
        $response = null;

        try {
            $response = $this->throttle($client) ?? $this->authorised($request, $client, array_values($abilities), $next);

            return $response;
        } catch (SerpException $e) {
            $status = $e->httpStatus();

            throw $e;
        } catch (ValidationException $e) {
            $status = 422;

            throw $e;
        } catch (HttpExceptionInterface $e) {
            $status = $e->getStatusCode();

            throw $e;
        } catch (Throwable $e) {
            $status = 500;

            throw $e;
        } finally {
            $this->recordUsage($request, $client, $response?->getStatusCode() ?? $status ?? 500, (int) ((hrtime(true) - $startedAt) / 1_000_000));
        }
    }

    /**
     * @param  array<int, string>  $abilities
     */
    private function authorised(Request $request, ApiClient $client, array $abilities, Closure $next): Response
    {
        foreach ($abilities as $ability) {
            if (! $client->hasAbility($ability)) {
                throw new InsufficientScopeException("This API key does not carry the [{$ability}] ability.", ['ability' => $ability]);
            }
        }

        return $next($request);
    }

    /**
     * BR-INT-04-003. Per-client sliding-minute limit from `api_clients.rate_limit_per_minute`.
     * Over the limit the answer is a 429 with `Retry-After`, never a silent drop.
     */
    private function throttle(ApiClient $client): ?Response
    {
        $key = 'api-client:'.$client->id;

        if (RateLimiter::tooManyAttempts($key, $client->rate_limit_per_minute)) {
            event(new RateLimitExceeded($client));
            $retryAfter = RateLimiter::availableIn($key);

            return ApiResponse::error('RATE_LIMITED', 'Rate limit exceeded for this API key.', 429, ['retry_after' => $retryAfter])
                ->withHeaders([
                    'Retry-After' => (string) $retryAfter,
                    'X-RateLimit-Limit' => (string) $client->rate_limit_per_minute,
                    'X-RateLimit-Remaining' => '0',
                ]);
        }

        RateLimiter::hit($key, 60);

        return null;
    }

    /**
     * §2 api_usage_log (feeds `Usage\Dashboard`, BR-INT-04-011) and `last_used_at`.
     */
    private function recordUsage(Request $request, ApiClient $client, int $status, int $durationMs): void
    {
        ApiUsageLog::create([
            'school_id' => $client->school_id,
            'client_id' => $client->id,
            'endpoint' => mb_substr('/'.ltrim($request->path(), '/'), 0, 200),
            'method' => $request->method(),
            'status_code' => $status,
            'duration_ms' => $durationMs,
            'occurred_at' => Carbon::now(),
        ]);

        $client->forceFill(['last_used_at' => Carbon::now()])->saveQuietly();
    }

    private function resolveClient(string $bearer): ?ApiClient
    {
        [$ulid, $secret] = array_pad(explode('.', $bearer, 2), 2, '');

        if ($ulid === '' || $secret === '') {
            return null;
        }

        $client = ApiClient::withoutSchoolScope()->where('ulid', $ulid)->first();

        if ($client === null || ! $client->is_active || $client->revoked_at !== null || ! Hash::check($bearer, $client->api_key_hash)) {
            return null;
        }

        return $client;
    }
}

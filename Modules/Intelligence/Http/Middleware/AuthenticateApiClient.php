<?php

declare(strict_types=1);

namespace Modules\Intelligence\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Domain\Exceptions\InsufficientScopeException;
use Modules\Core\Domain\Support\SchoolContext;
use Modules\Core\Http\Support\ApiResponse;
use Modules\Intelligence\Models\ApiClient;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Book J INT-04 §3 ⭐/BR-INT-04-001/002/007. Authenticates a third-party or
 * device credential, which is an `api_clients` key and never a Sanctum user token.
 * The bearer is `{client ulid}.{secret}`; the ulid finds the row and the full key is
 * verified against `api_key_hash`. A revoked or inactive client, a caller outside
 * the client's IP allowlist and a client missing a route's declared abilities
 * (`serp.api-client:attendance:write`) are each refused here, on every request, whatever
 * else is configured (AC-INT-04-001). On success the client's school becomes the ambient
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

        foreach ($abilities as $ability) {
            if (! $client->hasAbility($ability)) {
                throw new InsufficientScopeException("This API key does not carry the [{$ability}] ability.", ['ability' => $ability]);
            }
        }

        return $next($request);
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

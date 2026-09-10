<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

/**
 * Book A Part 1.10, step 1. Resolves the tenant from the subdomain (web)
 * or the authenticated token's owner (API), before authentication runs.
 * 404 on an unknown tenant (BR: the installer/tenant routes this guards
 * are opt-in via the `serp.web` / `serp.api` middleware groups, never the
 * application's default groups, so this strictness never affects
 * tenant-agnostic routes).
 */
final class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolveFromSubdomain($request)
            ?? $this->resolveFromAuthenticatedUser($request);

        if ($tenant === null) {
            abort(404);
        }

        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }

    private function resolveFromSubdomain(Request $request): ?Tenant
    {
        $host = $request->getHost();
        $appHost = (string) parse_url((string) config('app.url'), PHP_URL_HOST);

        if ($appHost === '' || $host === $appHost || ! str_ends_with($host, '.'.$appHost)) {
            return null;
        }

        $subdomain = substr($host, 0, -1 * (strlen($appHost) + 1));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        return Tenant::where('slug', $subdomain)->first();
    }

    private function resolveFromAuthenticatedUser(Request $request): ?Tenant
    {
        $guards = array_filter(['web', 'sanctum'], fn (string $guard): bool => array_key_exists($guard, (array) config('auth.guards', [])));

        foreach ($guards as $guard) {
            $user = $request->user($guard);
            $school = $user?->primarySchool();

            if ($school !== null) {
                return $school->tenant;
            }
        }

        return null;
    }
}

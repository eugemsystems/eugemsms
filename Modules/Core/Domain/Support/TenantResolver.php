<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

use Illuminate\Http\Request;
use Modules\Core\Models\Tenant;

/**
 * Book A Part 1.10, step 1 — shared by `Modules\Core\Http\Middleware\ResolveTenant`
 * (which 404s a route when nothing resolves, appropriate for the app's
 * own tenant-scoped routes) and `Modules\Core\Http\Fortify\AuthenticateViaAction`
 * (which cannot 404 the whole login attempt just because tenant
 * resolution failed — a failed resolution there degrades to "invalid
 * credentials" instead, so as not to leak tenant-existence information
 * or take down logout/the bare login view along with it).
 */
final class TenantResolver
{
    public function resolveFromSubdomain(Request $request): ?Tenant
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

    public function resolveFromAuthenticatedUser(Request $request): ?Tenant
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

    /**
     * A self-hosted, single-school-group install (the norm for this
     * app's primary deployment model, per CLAUDE.md — subdomain-per-tenant
     * is a hosted-SaaS concern, not something a Herd/single-install
     * deployment ever sets up) has no subdomain to resolve against and,
     * before the very first login of a session, no authenticated user
     * either. Falling back to the sole tenant when the install genuinely
     * has only one never fires for a real multi-tenant deployment, where
     * more than one row makes the fallback intentionally ambiguous.
     */
    public function resolveSoleTenant(): ?Tenant
    {
        $tenants = Tenant::query()->limit(2)->get();

        return $tenants->count() === 1 ? $tenants->first() : null;
    }

    public function resolveForRequest(Request $request): ?Tenant
    {
        return $this->resolveFromSubdomain($request)
            ?? $this->resolveFromAuthenticatedUser($request)
            ?? $this->resolveSoleTenant();
    }
}

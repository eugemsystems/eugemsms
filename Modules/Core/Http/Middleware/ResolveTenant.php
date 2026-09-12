<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Support\TenantResolver;
use Symfony\Component\HttpFoundation\Response;

/**
 * Book A Part 1.10, step 1. Resolves the tenant from the subdomain (web)
 * or the authenticated token's owner (API), before authentication runs.
 * 404 on an unknown tenant (BR: the installer/tenant routes this guards
 * are opt-in via the `serp.web` / `serp.api` middleware groups, never the
 * application's default groups, so this strictness never affects
 * tenant-agnostic routes). Resolution logic itself lives in
 * `TenantResolver`, shared with `AuthenticateViaAction`, which needs the
 * same lookups but must never 404 a login attempt over it.
 */
final class ResolveTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->resolver->resolveForRequest($request);

        if ($tenant === null) {
            abort(404);
        }

        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}

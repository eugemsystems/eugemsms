<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\TwoFactorRequiredException;
use Modules\Core\Domain\Support\Auth\UserType;
use Symfony\Component\HttpFoundation\Response;

/**
 * Book J SAA-02 §3 ⭐/BR-SAA-02-001 ⭐. The vendor console's own gate —
 * a THIRD guard, entirely separate from `serp.web`/`serp.api`
 * (Book J §0.2): never in either group, never composed with
 * `serp.resolve-tenant`/`serp.subscription-active` (the vendor
 * console is cross-tenant, not scoped to any one tenant's context).
 *
 * Identity is `users.user_type === UserType::Vendor` — never a
 * permission grant, matching AC-SAA-02-001's own framing ("no
 * permission grants this path"); the highest school-level role in
 * existence still fails this check, because it checks WHO the user
 * IS, not what they can do. IP allowlisting is enforced only once
 * `services.vendor.ip_allowlist` is actually configured — an
 * unconfigured allowlist is "not yet set up for this environment",
 * the same permissive-when-unconfigured stance `HttpLicenceClient`
 * already takes, not a silent bypass of a deliberately-set
 * restriction.
 */
final class EnsureVendorGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->user_type !== UserType::Vendor) {
            abort(403, 'This console is available to vendor staff only.');
        }

        if (! $this->ipAllowlisted($request)) {
            abort(403, 'Access to the vendor console is not permitted from this network.');
        }

        if ($user->two_factor_confirmed_at === null) {
            throw new TwoFactorRequiredException('The vendor console requires two-factor authentication to be enabled.');
        }

        return $next($request);
    }

    private function ipAllowlisted(Request $request): bool
    {
        $allowlist = config('services.vendor.ip_allowlist', []);

        if ($allowlist === []) {
            return true;
        }

        return in_array($request->ip(), $allowlist, true);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

/**
 * Book A Part 1.10, step 2. Suspended tenants get read-only access and a
 * notice, never a hard lockout — SAA-01: "a school locked out mid-term
 * will never renew and will tell every other school." Cancelled tenants
 * are refused outright.
 */
final class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            return $next($request);
        }

        if ($tenant->status === 'cancelled') {
            abort(403, 'This subscription has been cancelled.');
        }

        if ($tenant->status === 'suspended') {
            $request->attributes->set('subscription_read_only', true);

            if (! $request->isMethodSafe()) {
                abort(403, "This school's subscription is suspended. Contact your account manager to restore full access.");
            }
        }

        return $next($request);
    }
}

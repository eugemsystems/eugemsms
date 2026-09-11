<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Exceptions\SubscriptionPastDueException;
use Modules\Core\Models\Tenant;
use Symfony\Component\HttpFoundation\Response;

/**
 * Book A Part 1.10, step 2 / Book J SAA-01 §3 ⭐ — the graceful
 * degradation middleware Book A's middleware stack promised and SAA-01
 * fully specifies. `Tenant::status` mirrors the tenant's active
 * `Modules\Saas\Models\Subscription::status` (every SAA-01 lifecycle
 * Action keeps the two in lockstep in one transaction — see
 * `Modules\Saas\Domain\Support\TransitionsSubscriptionStatus`), so this
 * never joins to `subscriptions` on every request.
 *
 * Six states: `trial`/`active` pass through untouched; `past_due`/
 * `grace` and `suspended` allow every read but refuse a write with a
 * clear resolution path; `cancelled` behaves like `suspended` except
 * reads AND `CORE-13`-style export routes keep working through the
 * retention window (BR-SAA-01-008). At every stage, a route named
 * `safeguarding.*` is exempt from all of this — a child's safety never
 * depends on an invoice (§3 ⭐, BR-SAA-01-005).
 */
final class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->attributes->get('tenant');

        if (! $tenant instanceof Tenant) {
            return $next($request);
        }

        $status = $tenant->status;

        if (in_array($status, ['active', 'trial'], true)) {
            return $next($request);
        }

        $request->attributes->set('subscription_read_only', true);

        if ($request->isMethodSafe()) {
            $response = $next($request);

            if (in_array($status, ['past_due', 'grace'], true)) {
                $response->headers->set('X-Subscription-Notice', 'Payment is overdue. Contact your account manager to restore full access.');
            }

            return $response;
        }

        if ($this->isSafeguardingCritical($request)) {
            return $next($request);
        }

        if (in_array($status, ['past_due', 'grace'], true)) {
            throw new SubscriptionPastDueException($this->paymentPortalUrl());
        }

        if ($status === 'cancelled' && $this->isExportCritical($request)) {
            return $next($request);
        }

        abort(403, $this->refusalMessage($status));
    }

    /**
     * BRD-08 routes are named `safeguarding.*` by convention — see
     * `.ai/rules` for this rule once Welfare's HTTP layer is built; no
     * route in the application carries that prefix yet, so this is
     * inert today and activates automatically the day one does.
     */
    private function isSafeguardingCritical(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return $routeName !== null && str_starts_with($routeName, 'safeguarding.');
    }

    /**
     * `CORE-13`'s full-data export path — named `export.*` by the same
     * convention (BR-SAA-01-008/AC-SAA-01-005).
     */
    private function isExportCritical(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        return $routeName !== null && str_starts_with($routeName, 'export.');
    }

    private function paymentPortalUrl(): string
    {
        return url('/account/billing');
    }

    private function refusalMessage(string $status): string
    {
        return match ($status) {
            'suspended' => "This school's subscription is suspended. Contact your account manager to restore full access.",
            'cancelled' => 'This subscription has been cancelled. Contact your account manager if you believe this is an error.',
            default => 'This action is not available for this subscription.',
        };
    }
}

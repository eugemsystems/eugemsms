<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Core\Domain\Support\Auth\TwoFactorRequirement;
use Symfony\Component\HttpFoundation\Response;

/**
 * BR-CORE-05-005: a user whose role requires 2FA "can reach no page but
 * 2FA enrolment until enrolled." `AuthenticateViaAction` already sends a
 * freshly-logged-in such user straight to `two-factor.setup`; this
 * middleware is what keeps them confined there on every request after
 * that too (a bookmark, a back-button, a new tab) until they actually
 * confirm enrolment (`two_factor_confirmed_at` set).
 */
final class EnsureTwoFactorIsEnrolled
{
    /**
     * Routes a not-yet-enrolled user must still be able to reach —
     * enrolling itself, and logging out to try a different account.
     *
     * @var array<int, string>
     */
    private const array EXCLUDED_ROUTE_NAMES = [
        'two-factor.setup',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.recovery-codes',
        'two-factor.regenerate-recovery-codes',
        'logout',
    ];

    public function __construct(
        private readonly TwoFactorRequirement $twoFactorRequirement,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if ($routeName !== null && in_array($routeName, self::EXCLUDED_ROUTE_NAMES, true)) {
            return $next($request);
        }

        // Livewire's own background update endpoint (`X-Livewire` header,
        // sent on every `wire:click`/`wire:submit`/etc. call — see
        // `HandleRequests::isLivewireRequest()`) is a SINGLE shared route
        // for every component in the app, never named `two-factor.*`, so
        // the excluded-route-name check above can never match it. Without
        // this, every Livewire action on the enrolment screen itself
        // (Enable/Confirm/Disable) silently redirected instead of running
        // — the browser's `fetch()` followed the redirect body back as
        // if it were a Livewire response, which it can't parse, so the
        // button visibly did nothing (2026-09-12, user-reported: "the
        // button to enable 2 factor authentication ... is not working").
        // The page itself was already gated correctly on its own initial
        // GET request; this only lets an already-open page's Livewire
        // calls keep working, it doesn't grant access to a new page.
        if ($request->hasHeader('X-Livewire')) {
            return $next($request);
        }

        if (! $this->twoFactorRequirement->isRequiredFor($user)) {
            return $next($request);
        }

        return redirect()->route('two-factor.setup');
    }
}

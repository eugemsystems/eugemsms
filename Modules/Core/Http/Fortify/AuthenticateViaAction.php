<?php

declare(strict_types=1);

namespace Modules\Core\Http\Fortify;

use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Fortify;
use Modules\Core\Domain\Actions\Auth\AuthenticateWebAction;
use Modules\Core\Domain\DataObjects\Auth\WebLoginData;
use Modules\Core\Domain\Exceptions\AuthorisationException;
use Modules\Core\Domain\Support\TenantResolver;

/**
 * Replaces Fortify's default login pipeline stages
 * (`RedirectIfTwoFactorAuthenticatable` + `AttemptToAuthenticate`) with a
 * single stage — registered via `Fortify::authenticateThrough()` in
 * `FortifyServiceProvider`. Fortify's own two stages each independently
 * call `Fortify::$authenticateUsingCallback` (once to decide whether to
 * redirect to the 2FA challenge, again to actually log in if not), which
 * would run `AuthenticateWebAction` — and its side effects (a
 * `login_attempts` row, failed-count increments, `last_login_at`) —
 * twice per request. Calling it exactly once here avoids that.
 *
 * Book A CORE-05 §4/BR-CORE-05-005: an already-2FA-enrolled user is sent
 * to Fortify's own `two-factor.login` challenge exactly as stock Fortify
 * would. A user whose role *requires* 2FA but hasn't enrolled yet is
 * logged in immediately (nothing to challenge against) and sent straight
 * to enrolment instead — `EnsureTwoFactorIsEnrolled` middleware then
 * keeps them there for every other request until they confirm it.
 */
final class AuthenticateViaAction
{
    public function __construct(
        private readonly StatefulGuard $guard,
        private readonly TenantResolver $tenantResolver,
    ) {}

    public function handle(Request $request, callable $next): mixed
    {
        try {
            $result = app(AuthenticateWebAction::class)->execute(new WebLoginData(
                identifier: (string) $request->input(Fortify::username()),
                password: (string) $request->input('password'),
                tenantId: $this->tenantResolver->resolveForRequest($request)?->id,
                ip: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        } catch (AuthorisationException $e) {
            event(new Failed($this->guard->name ?? 'web', null, [
                Fortify::username() => $request->input(Fortify::username()),
                'password' => $request->input('password'),
            ]));

            throw ValidationException::withMessages([
                Fortify::username() => [$e->getMessage()],
            ]);
        }

        $user = $result->user;

        if (! $result->requiresTwoFactor) {
            $this->guard->login($user, $request->boolean('remember'));

            return $next($request);
        }

        if ($user->two_factor_confirmed_at !== null) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);

            TwoFactorAuthenticationChallenged::dispatch($user);

            return redirect()->route('two-factor.login');
        }

        $this->guard->login($user, $request->boolean('remember'));

        return redirect()->route('two-factor.setup');
    }
}

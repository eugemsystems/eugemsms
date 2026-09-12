<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Domain\Support\Auth\TwoFactorRequirement;
use Modules\Core\Http\Middleware\EnsureTwoFactorIsEnrolled;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;

function twoFactorRequestFor(User $user, ?string $routeName = null, bool $isLivewireRequest = false): Request
{
    $request = Request::create('/some-page');
    $request->setUserResolver(fn () => $user);

    if ($routeName !== null) {
        $route = new Route(['GET'], '/some-page', []);
        $route->name($routeName);
        $request->setRouteResolver(fn () => $route);
    }

    if ($isLivewireRequest) {
        $request->headers->set('X-Livewire', 'true');
    }

    return $request;
}

function userRequiringTwoFactor(): User
{
    $school = School::factory()->create();
    $role = Role::factory()->forSchool($school->id)->create(['name' => 'super_admin']);
    $user = User::factory()->create(['tenant_id' => $school->tenant_id, 'two_factor_confirmed_at' => null]);
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($user->id, $role->id, $school->id));

    return $user;
}

it('redirects a not-yet-enrolled, 2FA-required user away from an ordinary page', function (): void {
    $user = userRequiringTwoFactor();

    $response = (new EnsureTwoFactorIsEnrolled(app(TwoFactorRequirement::class)))
        ->handle(twoFactorRequestFor($user, 'dashboard'), fn () => response('ok'));

    expect($response->isRedirect(route('two-factor.setup')))->toBeTrue();
});

it('does not redirect a Livewire background update request, even for a not-yet-enrolled 2FA-required user (2026-09-12 bugfix)', function (): void {
    $user = userRequiringTwoFactor();

    // No route name at all here — Livewire's shared update endpoint is
    // never named `two-factor.*`, so the excluded-route-name check can
    // never catch it; this proves the `X-Livewire` header check is what
    // actually lets it through, not an accidental route-name match.
    $response = (new EnsureTwoFactorIsEnrolled(app(TwoFactorRequirement::class)))
        ->handle(twoFactorRequestFor($user, routeName: null, isLivewireRequest: true), fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('still passes through an excluded route name for a full page load', function (): void {
    $user = userRequiringTwoFactor();

    $response = (new EnsureTwoFactorIsEnrolled(app(TwoFactorRequirement::class)))
        ->handle(twoFactorRequestFor($user, 'two-factor.setup'), fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('does not redirect a user whose role does not require 2FA', function (): void {
    $user = User::factory()->create(['two_factor_confirmed_at' => null]);

    $response = (new EnsureTwoFactorIsEnrolled(app(TwoFactorRequirement::class)))
        ->handle(twoFactorRequestFor($user, 'dashboard'), fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

it('does not redirect a user who has already confirmed 2FA', function (): void {
    $user = userRequiringTwoFactor();
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $response = (new EnsureTwoFactorIsEnrolled(app(TwoFactorRequirement::class)))
        ->handle(twoFactorRequestFor($user, 'dashboard'), fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

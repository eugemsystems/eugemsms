<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;

/**
 * HTTP-level coverage for `Modules\Core\Http\Fortify\AuthenticateViaAction`
 * (the custom Fortify login pipeline stage) and `EnsureTwoFactorIsEnrolled`
 * middleware — `AuthenticateWebActionTest.php` already covers the Action
 * itself directly, per the "unit test that calls it without HTTP" rule;
 * this file exists because the Fortify wiring (routing to the 2FA
 * challenge vs. forced enrolment, calling the Action exactly once, the
 * tenant fallback) can only be proven through a real request.
 */
/**
 * Also aligns the user's `tenant_id` with the new role's school —
 * otherwise this helper's own `School::factory()` call creates a
 * second, unrelated tenant, which would then win the login pipeline's
 * sole-tenant fallback (`TenantResolver::resolveSoleTenant()`) over the
 * user's real (null, by `UserFactory`'s own default) tenant, making the
 * very next login attempt in the test fail to find the user at all.
 */
function assignRequiredTwoFactorRole(User $user): void
{
    $school = School::factory()->create();
    $role = Role::factory()->forSchool($school->id)->create(['name' => 'super_admin']);

    $user->forceFill(['tenant_id' => $school->tenant_id])->save();

    app(AssignRoleAction::class)->execute(new RoleAssignmentData($user->id, $role->id, $school->id));
}

it('logs a user in and redirects to the dashboard when 2FA is not required', function (): void {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

it('sends an unenrolled, role-required user straight to 2FA setup and logs them in immediately', function (): void {
    $user = User::factory()->create();
    assignRequiredTwoFactorRole($user);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.setup'));
    $this->assertAuthenticatedAs($user);
});

it('keeps a role-required, unenrolled user confined to the 2FA setup screen on every other request', function (): void {
    $user = User::factory()->create();
    assignRequiredTwoFactorRole($user);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('two-factor.setup'));
});

it('lets a role-required, unenrolled user reach the 2FA setup screen and log out without a redirect loop', function (): void {
    $user = User::factory()->create();
    assignRequiredTwoFactorRole($user);

    $this->actingAs($user)->get(route('two-factor.setup'))->assertOk();
    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
});

it('does not confine a user who does not need 2FA to any particular screen', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

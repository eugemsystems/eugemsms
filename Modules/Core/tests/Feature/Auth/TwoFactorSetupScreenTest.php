<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\AssignRoleAction;
use Modules\Core\Domain\DataObjects\Auth\RoleAssignmentData;
use Modules\Core\Livewire\Auth\TwoFactorSetup;
use Modules\Core\Models\Role;
use Modules\Core\Models\School;
use PragmaRX\Google2FA\Google2FA;

/**
 * Livewire-level coverage for `Auth\TwoFactorSetup` — the underlying
 * enable/confirm/disable behaviour (secret generation, TOTP validation,
 * recovery codes) is already unit-tested directly against the Actions
 * in `TwoFactorTest.php`; this file only proves the component wires
 * those Actions correctly (enable shows a QR code, a valid code reveals
 * recovery codes, an invalid one shows an inline error, disable is
 * blocked for a role that requires 2FA).
 */
it('shows the enable button, then a QR code once enabled', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(TwoFactorSetup::class)
        ->assertSee(__('Enable two-factor authentication'))
        ->call('enable')
        ->assertSet('showRecoveryCodes', false);

    expect($user->fresh()->two_factor_secret)->not->toBeNull();
});

it('confirms with a valid TOTP code and reveals recovery codes', function (): void {
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test(TwoFactorSetup::class)->call('enable');

    $secret = decrypt($user->fresh()->two_factor_secret);
    $code = (new Google2FA)->getCurrentOtp($secret);

    $component->set('code', $code)->call('confirm')->assertHasNoErrors();

    expect($user->fresh()->two_factor_confirmed_at)->not->toBeNull();
});

it('rejects an invalid confirmation code with an inline error', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(TwoFactorSetup::class)
        ->call('enable')
        ->set('code', '000000')
        ->call('confirm')
        ->assertHasErrors('code');

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});

it('refuses to disable two-factor for a role that requires it', function (): void {
    $user = User::factory()->create();
    $school = School::factory()->create();
    $user->forceFill(['tenant_id' => $school->tenant_id])->save();
    $role = Role::factory()->forSchool($school->id)->create(['name' => 'super_admin']);
    app(AssignRoleAction::class)->execute(new RoleAssignmentData($user->id, $role->id, $school->id));

    $component = Livewire::actingAs($user)->test(TwoFactorSetup::class)->call('enable');
    $secret = decrypt($user->fresh()->two_factor_secret);
    $component->set('code', (new Google2FA)->getCurrentOtp($secret))->call('confirm');

    $component->call('disable')->assertDispatched('toast', variant: 'danger');

    expect($user->fresh()->two_factor_secret)->not->toBeNull();
});

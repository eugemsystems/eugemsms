<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\IssueApiTokenAction;
use Modules\Core\Domain\DataObjects\Auth\DeviceData;
use Modules\Core\Domain\DataObjects\Auth\IssueTokenData;
use Modules\Core\Livewire\Profile\Security;

/**
 * `Core\Profile\Security` (Book A CORE-05 §6) — the signed-in user's own
 * password change, 2FA summary link, and "log out other devices". The
 * underlying Actions (`ChangePasswordAction`, `RevokeAllUserTokensAction`)
 * already have their own unit coverage in `Auth/PasswordTest.php` and
 * `Auth/ApiTokenLifecycleTest.php` — this file only proves the component
 * wires them correctly and surfaces their outcomes to the user.
 */
it('changes the password when the current one is correct', function (): void {
    $user = User::factory()->create(['password' => Hash::make('OldPassword9')]);

    Livewire::actingAs($user)
        ->test(Security::class)
        ->set('current_password', 'OldPassword9')
        ->set('password', 'NewPassword9!')
        ->set('password_confirmation', 'NewPassword9!')
        ->call('changePassword')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $fresh = $user->fresh();
    expect(Hash::check('NewPassword9!', $fresh->password))->toBeTrue()
        ->and($fresh->must_change_password)->toBeFalse();
});

it('shows an inline error when the current password is wrong', function (): void {
    $user = User::factory()->create(['password' => Hash::make('OldPassword9')]);

    Livewire::actingAs($user)
        ->test(Security::class)
        ->set('current_password', 'NotTheCurrentOne')
        ->set('password', 'NewPassword9!')
        ->set('password_confirmation', 'NewPassword9!')
        ->call('changePassword')
        ->assertHasErrors('current_password');

    expect(Hash::check('OldPassword9', $user->fresh()->password))->toBeTrue();
});

it('shows an inline error when the new password is weak', function (): void {
    $user = User::factory()->create(['password' => Hash::make('OldPassword9')]);

    Livewire::actingAs($user)
        ->test(Security::class)
        ->set('current_password', 'OldPassword9')
        ->set('password', 'weak')
        ->set('password_confirmation', 'weak')
        ->call('changePassword')
        ->assertHasErrors('password');

    expect(Hash::check('OldPassword9', $user->fresh()->password))->toBeTrue();
});

it('requires the new password confirmation to match', function (): void {
    $user = User::factory()->create(['password' => Hash::make('OldPassword9')]);

    Livewire::actingAs($user)
        ->test(Security::class)
        ->set('current_password', 'OldPassword9')
        ->set('password', 'NewPassword9!')
        ->set('password_confirmation', 'SomethingElse9!')
        ->call('changePassword')
        ->assertHasErrors('password');
});

it('shows whether two-factor authentication is enabled and links to its setup screen', function (): void {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(Security::class)
        ->assertSee(__('Not enabled'))
        ->assertSee(route('two-factor.setup'), false);

    $user->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    Livewire::actingAs($user->fresh())
        ->test(Security::class)
        ->assertSee(__('Enabled'));
});

it('logs out every other device without touching the current web session', function (): void {
    $user = User::factory()->create();
    app(IssueApiTokenAction::class)->execute(new IssueTokenData($user->id, new DeviceData(name: 'Phone', id: 'device-1')));
    app(IssueApiTokenAction::class)->execute(new IssueTokenData($user->id, new DeviceData(name: 'Tablet', id: 'device-2')));

    Livewire::actingAs($user)
        ->test(Security::class)
        ->call('logOutOtherDevices')
        ->assertDispatched('toast');

    expect($user->tokens()->whereNull('revoked_at')->count())->toBe(0);
});

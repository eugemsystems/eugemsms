<?php

use App\Models\User;
use Livewire\Livewire;
use Modules\Core\Domain\Actions\Auth\IssueApiTokenAction;
use Modules\Core\Domain\DataObjects\Auth\DeviceData;
use Modules\Core\Domain\DataObjects\Auth\IssueTokenData;
use Modules\Core\Livewire\Profile\Devices;

/**
 * `Core\Profile\Devices` (Book A CORE-05 §6) — the signed-in user's own
 * Sanctum device list and per-device revoke. Token issuance/revocation
 * Actions already have their own coverage in `Auth/ApiTokenLifecycleTest.php`;
 * this file proves the component lists only the current user's own
 * active tokens and wires the revoke button to `RevokeTokenAction`.
 */
function profileDevicesFixture(): User
{
    $user = User::factory()->create();

    app(IssueApiTokenAction::class)->execute(
        new IssueTokenData($user->id, new DeviceData(name: 'Alice\'s Phone', id: 'device-1', platform: 'android'))
    );
    app(IssueApiTokenAction::class)->execute(
        new IssueTokenData($user->id, new DeviceData(name: 'Alice\'s Tablet', id: 'device-2', platform: 'ios'))
    );

    return $user;
}

it('lists only the signed-in user\'s own active devices', function (): void {
    $user = profileDevicesFixture();
    $otherUser = User::factory()->create();
    app(IssueApiTokenAction::class)->execute(
        new IssueTokenData($otherUser->id, new DeviceData(name: 'Someone else\'s phone', id: 'device-other'))
    );

    Livewire::actingAs($user)
        ->test(Devices::class)
        ->assertSee('Alice\'s Phone')
        ->assertSee('Alice\'s Tablet')
        ->assertDontSee('Someone else\'s phone');
});

it('revokes a device belonging to the current user', function (): void {
    $user = profileDevicesFixture();
    $tokenId = $user->tokens()->where('device_id', 'device-1')->sole()->id;

    Livewire::actingAs($user)
        ->test(Devices::class)
        ->call('revoke', $tokenId)
        ->assertDispatched('toast');

    expect($user->tokens()->find($tokenId)->isRevoked())->toBeTrue()
        ->and($user->tokens()->whereNull('revoked_at')->count())->toBe(1);
});

it('is a harmless no-op revoking an already-revoked device', function (): void {
    $user = profileDevicesFixture();
    $tokenId = $user->tokens()->where('device_id', 'device-1')->sole()->id;

    $component = Livewire::actingAs($user)->test(Devices::class);
    $component->call('revoke', $tokenId);
    $component->call('revoke', $tokenId);

    expect($user->tokens()->find($tokenId)->isRevoked())->toBeTrue();
});

it('refuses to revoke a device belonging to another user', function (): void {
    $user = profileDevicesFixture();
    $otherUser = User::factory()->create();
    app(IssueApiTokenAction::class)->execute(
        new IssueTokenData($otherUser->id, new DeviceData(name: 'Someone else\'s phone', id: 'device-other'))
    );
    $otherTokenId = $otherUser->tokens()->where('device_id', 'device-other')->sole()->id;

    Livewire::actingAs($user)
        ->test(Devices::class)
        ->call('revoke', $otherTokenId);

    expect($otherUser->tokens()->find($otherTokenId)->isRevoked())->toBeFalse();
});

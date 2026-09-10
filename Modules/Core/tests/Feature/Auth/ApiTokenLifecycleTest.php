<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Auth\IssueApiTokenAction;
use Modules\Core\Domain\Actions\Auth\RefreshApiTokenAction;
use Modules\Core\Domain\Actions\Auth\RevokeAllUserTokensAction;
use Modules\Core\Domain\Actions\Auth\RevokeTokenAction;
use Modules\Core\Domain\DataObjects\Auth\DeviceData;
use Modules\Core\Domain\DataObjects\Auth\IssueTokenData;
use Modules\Core\Domain\DataObjects\Auth\RefreshTokenData;
use Modules\Core\Domain\DataObjects\Auth\RevokeTokenData;
use Modules\Core\Domain\Exceptions\TokenReuseDetectedException;
use Modules\Core\Domain\Registry\SettingDefinitionRegistry;
use Modules\Core\Models\PersonalAccessToken;
use Modules\Core\Models\RefreshToken;

function issueDevice(string $id = 'device-1'): DeviceData
{
    return new DeviceData(name: 'Test Phone', id: $id, platform: 'android');
}

it('issues an access/refresh token pair with the requested abilities', function (): void {
    $user = User::factory()->create();

    $pair = app(IssueApiTokenAction::class)->execute(new IssueTokenData($user->id, issueDevice(), ['fees:read']));

    expect($pair->accessToken)->toContain('|')
        ->and($pair->refreshToken)->not->toBeEmpty()
        ->and($pair->abilities)->toBe(['fees:read']);

    $this->assertDatabaseHas('personal_access_tokens', ['device_id' => 'device-1']);
    $this->assertDatabaseHas('refresh_tokens', ['device_id' => 'device-1']);
});

it('revokes the least recently used device once the device limit is reached (BR-CORE-05-010)', function (): void {
    $user = User::factory()->create();
    $action = app(IssueApiTokenAction::class);

    $default = (int) SettingDefinitionRegistry::all()['auth.max_devices_per_user']['default_value'];
    expect($default)->toBe(5);

    for ($i = 1; $i <= 5; $i++) {
        $action->execute(new IssueTokenData($user->id, issueDevice("device-{$i}")));
        PersonalAccessToken::where('device_id', "device-{$i}")->update(['last_used_at' => now()->addMinutes($i)]);
    }

    expect($user->tokens()->whereNull('revoked_at')->count())->toBe(5);

    $action->execute(new IssueTokenData($user->id, issueDevice('device-6')));

    expect($user->tokens()->whereNull('revoked_at')->count())->toBe(5)
        ->and(PersonalAccessToken::where('device_id', 'device-1')->first()->isRevoked())->toBeTrue();
});

it('rotates a refresh token on use and revokes the previous access token', function (): void {
    $user = User::factory()->create();
    $pair = app(IssueApiTokenAction::class)->execute(new IssueTokenData($user->id, issueDevice()));

    $newPair = app(RefreshApiTokenAction::class)->execute(new RefreshTokenData($pair->refreshToken));

    expect($newPair->accessToken)->not->toBe($pair->accessToken);

    $originalHash = hash('sha256', $pair->refreshToken);
    $original = RefreshToken::where('token_hash', $originalHash)->sole();
    expect($original->isUsed())->toBeTrue()
        ->and($original->replaced_by_id)->not->toBeNull();
});

it('revokes the entire device family when a used refresh token is replayed (BR-CORE-05-009/AC-CORE-05-002)', function (): void {
    $user = User::factory()->create();
    $pair = app(IssueApiTokenAction::class)->execute(new IssueTokenData($user->id, issueDevice()));

    app(RefreshApiTokenAction::class)->execute(new RefreshTokenData($pair->refreshToken));

    expect(fn () => app(RefreshApiTokenAction::class)->execute(new RefreshTokenData($pair->refreshToken)))
        ->toThrow(TokenReuseDetectedException::class);

    expect($user->tokens()->whereNull('revoked_at')->count())->toBe(0);
});

it('revoking a single token also invalidates its paired refresh token', function (): void {
    $user = User::factory()->create();
    $pair = app(IssueApiTokenAction::class)->execute(new IssueTokenData($user->id, issueDevice()));
    $accessTokenId = (int) explode('|', $pair->accessToken)[0];

    app(RevokeTokenAction::class)->execute(new RevokeTokenData($accessTokenId, $user->id));

    expect(PersonalAccessToken::find($accessTokenId)->isRevoked())->toBeTrue();
    expect(RefreshToken::where('access_token_id', $accessTokenId)->sole()->isUsed())->toBeTrue();
});

it('revokes every active token for a user (BR-CORE-05-020)', function (): void {
    $user = User::factory()->create();
    app(IssueApiTokenAction::class)->execute(new IssueTokenData($user->id, issueDevice('a')));
    app(IssueApiTokenAction::class)->execute(new IssueTokenData($user->id, issueDevice('b')));

    $count = app(RevokeAllUserTokensAction::class)->execute($user);

    expect($count)->toBe(2)
        ->and($user->tokens()->whereNull('revoked_at')->count())->toBe(0);
});

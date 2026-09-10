<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Domain\Actions\Auth\AuthenticateWebAction;
use Modules\Core\Domain\DataObjects\Auth\WebLoginData;
use Modules\Core\Domain\Exceptions\AccountLockedException;
use Modules\Core\Domain\Exceptions\InvalidCredentialsException;
use Modules\Core\Domain\Support\Auth\UserStatus;
use Modules\Core\Models\Tenant;

function loginData(string $identifier, string $password, ?int $tenantId, ?string $ip = '10.0.0.1'): WebLoginData
{
    return new WebLoginData($identifier, $password, $tenantId, $ip, 'PestBrowser/1.0');
}

it('authenticates a user with the correct password', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'ok@example.com', 'password' => Hash::make('correct-password')]);

    $result = app(AuthenticateWebAction::class)->execute(loginData('ok@example.com', 'correct-password', $tenant->id));

    expect($result->user->is($user))->toBeTrue()
        ->and($result->requiresTwoFactor)->toBeFalse();
});

it('rejects the wrong password and writes a login_attempts row (BR-CORE-05-007)', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'ok@example.com', 'password' => Hash::make('correct-password')]);

    expect(fn () => app(AuthenticateWebAction::class)->execute(loginData('ok@example.com', 'wrong-password', $tenant->id)))
        ->toThrow(InvalidCredentialsException::class);

    $this->assertDatabaseHas('login_attempts', ['identifier' => 'ok@example.com', 'was_successful' => false]);
});

it('locks the account after the configured number of failed attempts (BR-CORE-05-006)', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'ok@example.com', 'password' => Hash::make('correct-password')]);

    for ($i = 0; $i < 5; $i++) {
        try {
            app(AuthenticateWebAction::class)->execute(loginData('ok@example.com', 'wrong', $tenant->id, "10.0.0.{$i}"));
        } catch (InvalidCredentialsException) {
            // expected
        }
    }

    expect($user->fresh()->isLocked())->toBeTrue();

    expect(fn () => app(AuthenticateWebAction::class)->execute(loginData('ok@example.com', 'correct-password', $tenant->id, '10.0.0.99')))
        ->toThrow(AccountLockedException::class);
});

it('refuses a non-active account even with the right password', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'suspended@example.com',
        'password' => Hash::make('correct-password'),
        'status' => UserStatus::Suspended,
    ]);

    expect(fn () => app(AuthenticateWebAction::class)->execute(loginData('suspended@example.com', 'correct-password', $tenant->id)))
        ->toThrow(AccountLockedException::class);
});

it('throttles by IP address across different identifiers (BR-CORE-05-006)', function (): void {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'a@example.com', 'password' => Hash::make('secret-pass')]);
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'b@example.com', 'password' => Hash::make('secret-pass')]);

    for ($i = 0; $i < 5; $i++) {
        try {
            app(AuthenticateWebAction::class)->execute(loginData('a@example.com', 'wrong', $tenant->id, '10.0.0.50'));
        } catch (InvalidCredentialsException) {
            // expected
        }
    }

    expect(fn () => app(AuthenticateWebAction::class)->execute(loginData('b@example.com', 'secret-pass', $tenant->id, '10.0.0.50')))
        ->toThrow(AccountLockedException::class);
});

<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Install\CreateSuperAdminAction;
use Modules\Core\Domain\DataObjects\Install\SuperAdminData;

beforeEach(function (): void {
    // Password::uncompromised() calls the real "have I been pwned" API —
    // faked so the test suite never depends on network access.
    Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 200)]);
});

it('creates the super administrator with a verified email and no confirmed 2FA yet', function (): void {
    $user = (new CreateSuperAdminAction)->execute(new SuperAdminData(
        name: 'Head Admin',
        email: 'head@example.test',
        password: 'a-genuinely-unusual-passphrase-93!',
    ));

    expect($user->exists)->toBeTrue()
        ->and($user->email)->toBe('head@example.test')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull();
});

it('rejects an invalid email', function (): void {
    (new CreateSuperAdminAction)->execute(new SuperAdminData(
        name: 'Head Admin',
        email: 'not-an-email',
        password: 'a-genuinely-unusual-passphrase-93!',
    ));
})->throws(ValidationException::class);

<?php

use App\Models\User;
use Modules\Core\Domain\Actions\Auth\ConfirmTwoFactorAction;
use Modules\Core\Domain\Actions\Auth\DisableTwoFactorAction;
use Modules\Core\Domain\Actions\Auth\EnableTwoFactorAction;
use Modules\Core\Domain\DataObjects\Auth\ConfirmTwoFactorData;
use Modules\Core\Domain\DataObjects\Auth\DisableTwoFactorData;
use Modules\Core\Domain\DataObjects\Auth\EnableTwoFactorData;
use Modules\Core\Domain\Exceptions\InvalidTwoFactorCodeException;
use PragmaRX\Google2FA\Google2FA;

it('generates a two-factor secret and recovery codes on enable', function (): void {
    $user = User::factory()->create();

    $updated = app(EnableTwoFactorAction::class)->execute(new EnableTwoFactorData($user->id));

    expect($updated->two_factor_secret)->not->toBeNull()
        ->and($updated->two_factor_recovery_codes)->not->toBeNull()
        ->and($updated->two_factor_confirmed_at)->toBeNull();
});

it('confirms two-factor with a valid TOTP code', function (): void {
    $user = User::factory()->create();
    app(EnableTwoFactorAction::class)->execute(new EnableTwoFactorData($user->id));
    $user->refresh();

    $secret = decrypt($user->two_factor_secret);
    $code = (new Google2FA)->getCurrentOtp($secret);

    $confirmed = app(ConfirmTwoFactorAction::class)->execute(new ConfirmTwoFactorData($user->id, $code));

    expect($confirmed->two_factor_confirmed_at)->not->toBeNull();
});

it('rejects an invalid confirmation code', function (): void {
    $user = User::factory()->create();
    app(EnableTwoFactorAction::class)->execute(new EnableTwoFactorData($user->id));

    app(ConfirmTwoFactorAction::class)->execute(new ConfirmTwoFactorData($user->id, '000000'));
})->throws(InvalidTwoFactorCodeException::class);

it('disables two-factor and clears the secret', function (): void {
    $user = User::factory()->create();
    app(EnableTwoFactorAction::class)->execute(new EnableTwoFactorData($user->id));

    $updated = app(DisableTwoFactorAction::class)->execute(new DisableTwoFactorData($user->id));

    expect($updated->two_factor_secret)->toBeNull()
        ->and($updated->two_factor_confirmed_at)->toBeNull();
});

<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Domain\Actions\Auth\ChangePasswordAction;
use Modules\Core\Domain\Actions\Auth\ResetPasswordAction;
use Modules\Core\Domain\DataObjects\Auth\ChangePasswordData;
use Modules\Core\Domain\DataObjects\Auth\ResetPasswordData;
use Modules\Core\Domain\Exceptions\InvalidCredentialsException;
use Modules\Core\Domain\Exceptions\WeakPasswordException;

it('changes a password when the current one is correct', function (): void {
    $user = User::factory()->create(['password' => Hash::make('OldPassword9')]);

    app(ChangePasswordAction::class)->execute(new ChangePasswordData($user->id, 'OldPassword9', 'NewPassword9!'));

    expect(Hash::check('NewPassword9!', $user->fresh()->password))->toBeTrue()
        ->and($user->fresh()->must_change_password)->toBeFalse();
});

it('refuses to change the password when the current one is wrong', function (): void {
    $user = User::factory()->create(['password' => Hash::make('OldPassword9')]);

    app(ChangePasswordAction::class)->execute(new ChangePasswordData($user->id, 'NotTheCurrentOne', 'NewPassword9!'));
})->throws(InvalidCredentialsException::class);

it('refuses a weak new password', function (): void {
    $user = User::factory()->create(['password' => Hash::make('OldPassword9')]);

    app(ChangePasswordAction::class)->execute(new ChangePasswordData($user->id, 'OldPassword9', 'weak'));
})->throws(WeakPasswordException::class);

it('an administrative reset forces a change on next login', function (): void {
    $user = User::factory()->create(['password' => Hash::make('OldPassword9')]);
    $admin = User::factory()->create();

    app(ResetPasswordAction::class)->execute(new ResetPasswordData($user->id, 'TemporaryPass9!', $admin->id));

    $fresh = $user->fresh();
    expect(Hash::check('TemporaryPass9!', $fresh->password))->toBeTrue()
        ->and($fresh->must_change_password)->toBeTrue();
});

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\ChangePasswordData;
use Modules\Core\Domain\Exceptions\InvalidCredentialsException;
use Modules\Core\Domain\Exceptions\WeakPasswordException;
use Modules\Core\Domain\Support\Auth\PasswordPolicy;

final class ChangePasswordAction extends Action
{
    public function __construct(
        private readonly PasswordPolicy $passwordPolicy,
    ) {}

    public function execute(ChangePasswordData $data): void
    {
        $user = User::findOrFail($data->userId);

        if ($user->password === null || ! Hash::check($data->currentPassword, $user->password)) {
            throw new InvalidCredentialsException('The current password is incorrect.');
        }

        $errors = $this->passwordPolicy->validate($data->newPassword);

        if ($errors !== []) {
            throw new WeakPasswordException(implode(' ', $errors));
        }

        $this->transaction(function () use ($user, $data): void {
            $user->forceFill([
                'password' => $data->newPassword,
                'must_change_password' => false,
                'password_changed_at' => Carbon::now(),
            ])->save();
        });
    }
}

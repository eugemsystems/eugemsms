<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\ResetPasswordData;
use Modules\Core\Domain\Exceptions\WeakPasswordException;
use Modules\Core\Domain\Support\Auth\PasswordPolicy;

/**
 * ACT-ResetPassword (Book A CORE-05 §3) — the administrative/forced
 * reset path (`core.user.reset_password`), as distinct from
 * `ChangePasswordAction`'s self-service, current-password-verified one.
 * Sets `must_change_password` so the user is forced to set their own on
 * next login.
 */
final class ResetPasswordAction extends Action
{
    public function __construct(
        private readonly PasswordPolicy $passwordPolicy,
    ) {}

    public function execute(ResetPasswordData $data): void
    {
        $user = User::findOrFail($data->userId);

        $errors = $this->passwordPolicy->validate($data->newPassword);

        if ($errors !== []) {
            throw new WeakPasswordException(implode(' ', $errors));
        }

        $this->transaction(function () use ($user, $data): void {
            $user->forceFill([
                'password' => $data->newPassword,
                'must_change_password' => true,
                'password_changed_at' => Carbon::now(),
                'updated_by' => $data->resetByUserId,
            ])->save();
        });
    }
}

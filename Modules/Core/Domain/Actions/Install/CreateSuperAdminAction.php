<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Install;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Install\SuperAdminData;

/**
 * ACT-CreateSuperAdmin (Book A CORE-01 §3). BR-CORE-01-008: the password
 * must meet the configured policy and cannot be a known-breached
 * password. 2FA enrolment is forced before the installer hands off to
 * the dashboard (AC-CORE-01-001) — enforced by the installer's own
 * Administrator step, not a general app policy; persistent per-role 2FA
 * enforcement belongs to CORE-05.
 */
final class CreateSuperAdminAction extends Action
{
    public function execute(SuperAdminData $data): User
    {
        Validator::make(
            ['name' => $data->name, 'email' => $data->email, 'password' => $data->password],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'password' => ['required', 'string', Password::default()->uncompromised()],
            ],
        )->validate();

        return $this->transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data->name,
                'email' => $data->email,
                'password' => $data->password,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            return $user;
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\UpdateUserData;
use Modules\Core\Domain\Events\Auth\UserUpdated;
use Modules\Core\Domain\Exceptions\DuplicateRecordException;
use Modules\Core\Domain\Exceptions\IncompleteUserIdentityException;
use Modules\Core\Domain\Support\Auth\PhoneNormalizer;

/**
 * ACT-UpdateUser (Book A CORE-05 §3). Edits a user's identity fields —
 * never their password (`ChangePasswordAction`/`ResetPasswordAction`
 * own that) and never their status (`DeactivateUserAction` owns that,
 * BR-CORE-05-021). Re-validates BR-CORE-05-001 (at least one of email/
 * phone/username) and BR-CORE-05-002 (per-tenant uniqueness) exactly
 * like `CreateUserAction`, excluding the user's own row.
 */
final class UpdateUserAction extends Action
{
    public function execute(UpdateUserData $data): User
    {
        $user = User::findOrFail($data->userId);

        $phone = $data->phone !== null ? PhoneNormalizer::toE164($data->phone) : null;

        if ($data->email === null && $phone === null && $data->username === null) {
            throw new IncompleteUserIdentityException('A user must have at least one of email, phone, or username.');
        }

        $this->assertUnique('email', $data->email, $user);
        $this->assertUnique('phone', $phone, $user);
        $this->assertUnique('username', $data->username, $user);

        return $this->transaction(function () use ($user, $data, $phone): User {
            $user->forceFill([
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'other_names' => $data->otherNames,
                'name' => trim("{$data->firstName} {$data->lastName}"),
                'email' => $data->email,
                'phone' => $phone,
                'username' => $data->username,
                'user_type' => $data->userType,
                'locale' => $data->locale,
                'updated_by' => $data->updatedByUserId,
            ])->save();

            event(new UserUpdated($user));

            return $user;
        });
    }

    private function assertUnique(string $column, ?string $value, User $user): void
    {
        if ($value === null) {
            return;
        }

        $exists = User::withTrashed()
            ->where($column, $value)
            ->where('tenant_id', $user->tenant_id)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($exists) {
            throw new DuplicateRecordException("A user with this {$column} already exists.", [$column => $value]);
        }
    }
}

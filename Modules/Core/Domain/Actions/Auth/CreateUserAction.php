<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\CreateUserData;
use Modules\Core\Domain\Events\Auth\UserCreated;
use Modules\Core\Domain\Exceptions\DuplicateRecordException;
use Modules\Core\Domain\Exceptions\IncompleteUserIdentityException;
use Modules\Core\Domain\Exceptions\WeakPasswordException;
use Modules\Core\Domain\Support\Auth\PasswordPolicy;
use Modules\Core\Domain\Support\Auth\PhoneNormalizer;

/**
 * ACT-CreateUser (Book A CORE-05 §3). BR-CORE-05-001: at least one of
 * email, phone, or username must be present. `name` is kept in sync as
 * "{first} {last}" for the Fortify-driven login/profile views that
 * still read it — see the migration note in
 * `0009_01_01_000001_add_identity_columns_to_users_table.php`.
 */
final class CreateUserAction extends Action
{
    public function __construct(
        private readonly PasswordPolicy $passwordPolicy,
    ) {}

    public function execute(CreateUserData $data): User
    {
        $phone = $data->phone !== null ? PhoneNormalizer::toE164($data->phone) : null;

        if ($data->email === null && $phone === null && $data->username === null) {
            throw new IncompleteUserIdentityException('A user must have at least one of email, phone, or username.');
        }

        if ($data->password !== null) {
            $errors = $this->passwordPolicy->validate($data->password);

            if ($errors !== []) {
                throw new WeakPasswordException(implode(' ', $errors));
            }
        }

        $this->assertUnique('email', $data->email, $data->tenantId);
        $this->assertUnique('phone', $phone, $data->tenantId);
        $this->assertUnique('username', $data->username, $data->tenantId);

        return $this->transaction(function () use ($data, $phone): User {
            $user = User::create([
                'tenant_id' => $data->tenantId,
                'first_name' => $data->firstName,
                'last_name' => $data->lastName,
                'other_names' => $data->otherNames,
                'name' => trim("{$data->firstName} {$data->lastName}"),
                'email' => $data->email,
                'phone' => $phone,
                'username' => $data->username,
                'password' => $data->password,
                'user_type' => $data->userType,
                'locale' => $data->locale,
                'password_changed_at' => $data->password !== null ? Carbon::now() : null,
                'created_by' => $data->createdByUserId,
                'updated_by' => $data->createdByUserId,
            ]);

            event(new UserCreated($user));

            return $user;
        });
    }

    private function assertUnique(string $column, ?string $value, ?int $tenantId): void
    {
        if ($value === null) {
            return;
        }

        $exists = User::withTrashed()
            ->where($column, $value)
            ->where('tenant_id', $tenantId)
            ->exists();

        if ($exists) {
            throw new DuplicateRecordException("A user with this {$column} already exists.", [$column => $value]);
        }
    }
}

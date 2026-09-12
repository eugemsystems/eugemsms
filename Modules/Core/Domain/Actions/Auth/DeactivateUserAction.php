<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\DeactivateUserData;
use Modules\Core\Domain\Events\Auth\UserDeactivated;
use Modules\Core\Domain\Support\Auth\UserStatus;

/**
 * ACT-DeactivateUser (Book A CORE-05 §3/BR-CORE-05-020/BR-CORE-05-021).
 * The only "removal" this module offers — a user is never hard-deleted
 * while any audit/financial/approval record references them, so
 * deactivation (flip `status`, revoke every token) is the sole path.
 */
final class DeactivateUserAction extends Action
{
    public function __construct(
        private readonly RevokeAllUserTokensAction $revokeAllUserTokensAction,
    ) {}

    public function execute(DeactivateUserData $data): User
    {
        $user = User::findOrFail($data->userId);

        return $this->transaction(function () use ($user, $data): User {
            $user->forceFill([
                'status' => UserStatus::Inactive,
                'updated_by' => $data->deactivatedByUserId,
            ])->save();

            // BR-CORE-05-020: deactivating a user immediately revokes all
            // their tokens and terminates all their sessions.
            $this->revokeAllUserTokensAction->execute($user);

            event(new UserDeactivated($user));

            return $user;
        });
    }
}

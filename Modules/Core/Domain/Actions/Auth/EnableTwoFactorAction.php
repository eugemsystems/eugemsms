<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\EnableTwoFactorData;

/**
 * ACT-EnableTwoFactor (Book A CORE-05 §3). Generates the secret and
 * recovery codes via Fortify's own action — `User` already uses
 * `TwoFactorAuthenticatable`, so the columns, encryption, and the
 * `TwoFactorAuthenticationEnabled` event are Fortify's, unchanged.
 * Enrolment is not "enabled" for enforcement purposes until confirmed
 * — see `ConfirmTwoFactorAction`.
 */
final class EnableTwoFactorAction extends Action
{
    public function __construct(
        private readonly EnableTwoFactorAuthentication $fortifyEnable,
    ) {}

    public function execute(EnableTwoFactorData $data): User
    {
        $user = User::findOrFail($data->userId);

        ($this->fortifyEnable)($user);

        return $user->refresh();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\DisableTwoFactorData;

final class DisableTwoFactorAction extends Action
{
    public function __construct(
        private readonly DisableTwoFactorAuthentication $fortifyDisable,
    ) {}

    public function execute(DisableTwoFactorData $data): User
    {
        $user = User::findOrFail($data->userId);

        ($this->fortifyDisable)($user);

        return $user->refresh();
    }
}

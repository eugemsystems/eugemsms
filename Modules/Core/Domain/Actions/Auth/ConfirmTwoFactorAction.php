<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Auth;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\DataObjects\Auth\ConfirmTwoFactorData;
use Modules\Core\Domain\Events\Auth\TwoFactorEnabled;
use Modules\Core\Domain\Exceptions\InvalidTwoFactorCodeException;

final class ConfirmTwoFactorAction extends Action
{
    public function __construct(
        private readonly ConfirmTwoFactorAuthentication $fortifyConfirm,
    ) {}

    public function execute(ConfirmTwoFactorData $data): User
    {
        $user = User::findOrFail($data->userId);

        try {
            ($this->fortifyConfirm)($user, $data->code);
        } catch (ValidationException $e) {
            throw new InvalidTwoFactorCodeException($e->getMessage());
        }

        $user->refresh();

        event(new TwoFactorEnabled($user));

        return $user;
    }
}

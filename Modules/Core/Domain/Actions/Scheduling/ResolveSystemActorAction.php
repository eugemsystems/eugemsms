<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Actions\Scheduling;

use App\Models\User;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Support\Auth\UserStatus;
use Modules\Core\Domain\Support\Auth\UserType;

/**
 * The account scheduled jobs act as when an Action needs a `users.id` to
 * record (a generated work order's `raised_by`, for instance). It has no
 * password and an `inactive` status, so nobody can sign in as it, and it is
 * shared across schools — the school is carried by the record it creates.
 */
final class ResolveSystemActorAction extends Action
{
    public const EMAIL = 'system@serp.internal';

    public function execute(): int
    {
        $existing = User::query()->where('email', self::EMAIL)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return $this->transaction(fn (): int => User::query()->create([
            'name' => 'System',
            'email' => self::EMAIL,
            'password' => null,
            'user_type' => UserType::Staff,
            'status' => UserStatus::Inactive,
        ])->id);
    }
}

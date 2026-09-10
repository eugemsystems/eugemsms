<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Auth;

use App\Models\User;

final class UserLoggedIn
{
    public function __construct(
        public readonly User $user,
        public readonly string $guard,
    ) {}
}

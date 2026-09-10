<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Auth;

use App\Models\User;

final class TokenIssued
{
    public function __construct(
        public readonly User $user,
        public readonly int $accessTokenId,
    ) {}
}

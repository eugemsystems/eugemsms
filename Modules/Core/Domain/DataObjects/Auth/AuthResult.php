<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

use App\Models\User;

final readonly class AuthResult
{
    public function __construct(
        public User $user,
        public bool $requiresTwoFactor,
        public ?TokenPair $tokens = null,
    ) {}
}

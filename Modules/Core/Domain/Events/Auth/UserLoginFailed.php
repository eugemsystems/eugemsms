<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Auth;

final class UserLoginFailed
{
    public function __construct(
        public readonly string $identifier,
        public readonly string $reason,
    ) {}
}

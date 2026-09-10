<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Auth;

final class TokenRevoked
{
    public function __construct(
        public readonly int $tokenId,
        public readonly int $revokedByUserId,
    ) {}
}

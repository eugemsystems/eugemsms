<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class RevokeTokenData
{
    public function __construct(
        public int $tokenId,
        public int $revokedByUserId,
    ) {}
}

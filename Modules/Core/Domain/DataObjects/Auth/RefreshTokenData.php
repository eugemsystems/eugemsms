<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class RefreshTokenData
{
    public function __construct(
        public string $refreshToken,
        public ?string $ip = null,
    ) {}
}

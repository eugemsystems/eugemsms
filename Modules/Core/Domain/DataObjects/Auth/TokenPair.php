<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

use Illuminate\Support\Carbon;

final readonly class TokenPair
{
    /**
     * @param  array<int, string>  $abilities
     */
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public Carbon $accessTokenExpiresAt,
        public Carbon $refreshTokenExpiresAt,
        public array $abilities,
    ) {}
}

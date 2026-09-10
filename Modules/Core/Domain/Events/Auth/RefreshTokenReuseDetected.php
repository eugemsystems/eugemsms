<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Auth;

use App\Models\User;

/**
 * Book A CORE-05 BR-CORE-05-009/AC-CORE-05-002. Raised when an
 * already-used refresh token is presented again — the entire device
 * token family is revoked in response.
 */
final class RefreshTokenReuseDetected
{
    public function __construct(
        public readonly User $user,
        public readonly string $deviceId,
    ) {}
}

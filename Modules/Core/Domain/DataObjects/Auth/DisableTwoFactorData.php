<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class DisableTwoFactorData
{
    public function __construct(
        public int $userId,
    ) {}
}

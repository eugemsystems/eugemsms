<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class DeactivateUserData
{
    public function __construct(
        public int $userId,
        public ?int $deactivatedByUserId = null,
    ) {}
}

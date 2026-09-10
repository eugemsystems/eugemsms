<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class ChangePasswordData
{
    public function __construct(
        public int $userId,
        public string $currentPassword,
        public string $newPassword,
    ) {}
}

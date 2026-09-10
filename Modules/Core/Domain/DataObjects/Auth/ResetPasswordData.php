<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class ResetPasswordData
{
    public function __construct(
        public int $userId,
        public string $newPassword,
        public ?int $resetByUserId = null,
    ) {}
}

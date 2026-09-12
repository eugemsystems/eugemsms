<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Auth;

final class UserPermissionsChanged
{
    public function __construct(
        public readonly int $userId,
        public readonly int $schoolId,
    ) {}
}

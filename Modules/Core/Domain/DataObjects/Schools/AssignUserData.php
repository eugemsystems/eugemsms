<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class AssignUserData
{
    public function __construct(
        public int $schoolId,
        public int $userId,
        public int $assignedByUserId,
        public bool $isPrimary = false,
    ) {}
}

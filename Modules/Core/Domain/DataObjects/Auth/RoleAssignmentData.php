<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class RoleAssignmentData
{
    public function __construct(
        public int $userId,
        public int $roleId,
        public int $schoolId,
        public ?int $performedByUserId = null,
    ) {}
}

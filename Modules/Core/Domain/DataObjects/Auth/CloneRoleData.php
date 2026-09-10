<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class CloneRoleData
{
    public function __construct(
        public int $sourceRoleId,
        public int $schoolId,
        public string $name,
        public string $displayName,
        public ?int $clonedByUserId = null,
    ) {}
}

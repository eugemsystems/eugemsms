<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class UserPermissionData
{
    /**
     * @param  array<int, PermissionGrantData>  $grants
     */
    public function __construct(
        public int $userId,
        public int $schoolId,
        public array $grants,
        public ?int $updatedByUserId = null,
    ) {}
}

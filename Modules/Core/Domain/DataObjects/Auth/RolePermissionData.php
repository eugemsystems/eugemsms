<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

final readonly class RolePermissionData
{
    /**
     * @param  array<int, PermissionGrantData>  $grants
     */
    public function __construct(
        public int $roleId,
        public array $grants,
        public ?int $updatedByUserId = null,
    ) {}
}

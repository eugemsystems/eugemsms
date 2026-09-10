<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Auth;

use Modules\Core\Domain\Support\Auth\PermissionScope;

final readonly class PermissionGrantData
{
    public function __construct(
        public int $permissionId,
        public PermissionScope $scope,
    ) {}
}

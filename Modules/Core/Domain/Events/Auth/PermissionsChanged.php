<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Auth;

final class PermissionsChanged
{
    public function __construct(
        public readonly int $roleId,
    ) {}
}

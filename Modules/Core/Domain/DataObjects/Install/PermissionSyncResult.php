<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

final readonly class PermissionSyncResult
{
    public function __construct(
        public int $created,
        public int $updated,
        public int $total,
    ) {}
}

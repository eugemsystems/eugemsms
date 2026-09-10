<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Backups;

final readonly class RequestProductionRestoreData
{
    public function __construct(
        public int $backupId,
        public int $requestedByUserId,
        public string $reason,
    ) {}
}

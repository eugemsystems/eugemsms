<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Files;

final readonly class SetStorageQuotaData
{
    public function __construct(
        public int $schoolId,
        public int $quotaBytes,
        public int $warnAtPercent = 85,
    ) {}
}

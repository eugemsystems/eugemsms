<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Files;

final readonly class PurgeExpiredFilesData
{
    public function __construct(
        public int $retentionDays = 30,
    ) {}
}

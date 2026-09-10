<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ProcessStaffExitData
{
    public function __construct(
        public int $staffId,
        public string $exitReason,
        public CarbonInterface $exitedOn,
        public int $processedByUserId,
    ) {}
}

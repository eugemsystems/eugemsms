<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class GenerateAccountingExportData
{
    public function __construct(
        public int $schoolId,
        public string $targetSystem,
        public CarbonInterface $periodFrom,
        public CarbonInterface $periodTo,
        public int $exportedByUserId,
    ) {}
}

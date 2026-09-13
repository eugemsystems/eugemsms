<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class GenerateCollectionsReportData
{
    public function __construct(
        public int $schoolId,
        public string $fromDate,
        public string $toDate,
    ) {}
}

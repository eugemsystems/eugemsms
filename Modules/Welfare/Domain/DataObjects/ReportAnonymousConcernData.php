<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ReportAnonymousConcernData
{
    public function __construct(
        public int $schoolId,
        public string $concernCategory,
        public string $description,
        public CarbonInterface $reportedAt,
        public ?int $studentId = null,
        public bool $immediateRisk = false,
    ) {}
}

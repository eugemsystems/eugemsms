<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ReportSafeguardingConcernData
{
    public function __construct(
        public int $schoolId,
        public string $reportSource,
        public string $concernCategory,
        public string $description,
        public CarbonInterface $reportedAt,
        public ?int $studentId = null,
        public ?int $reporterUserId = null,
        public bool $immediateRisk = false,
        public ?string $initialActionTaken = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordRiskAssessmentData
{
    /**
     * @param  array<int, string>  $riskFactors
     * @param  array<int, string>|null  $protectiveFactors
     */
    public function __construct(
        public int $schoolId,
        public int $caseId,
        public int $assessedByUserId,
        public CarbonInterface $assessedAt,
        public array $riskFactors,
        public string $riskLevel,
        public string $rationale,
        public string $mitigationPlan,
        public CarbonInterface $reviewDueOn,
        public ?array $protectiveFactors = null,
    ) {}
}

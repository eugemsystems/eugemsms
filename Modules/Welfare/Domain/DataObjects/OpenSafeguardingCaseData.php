<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

final readonly class OpenSafeguardingCaseData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public int $leadStaffId,
        public int $openedByUserId,
        public string $category,
        public string $riskLevel,
        public string $summary,
        public ?int $concernId = null,
        public ?bool $guardiansInformed = null,
        public ?string $guardiansNotInformedReason = null,
    ) {}
}

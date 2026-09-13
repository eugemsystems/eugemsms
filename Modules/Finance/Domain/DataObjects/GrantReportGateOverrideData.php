<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class GrantReportGateOverrideData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public int $termId,
        public string $reason,
        public int $grantedByUserId,
    ) {}
}

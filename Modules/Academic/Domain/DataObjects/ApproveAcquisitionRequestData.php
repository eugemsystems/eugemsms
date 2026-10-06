<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ApproveAcquisitionRequestData
{
    public function __construct(
        public int $requestId,
        public int $approvedByUserId,
        public int $academicYearId,
        public int $termId,
        public int $departmentId,
        public int $costCentreId,
        public string $currency,
    ) {}
}

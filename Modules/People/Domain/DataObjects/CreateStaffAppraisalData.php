<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class CreateStaffAppraisalData
{
    public function __construct(
        public int $schoolId,
        public int $staffId,
        public int $academicYearId,
        public string $cycle,
        public int $appraiserStaffId,
    ) {}
}

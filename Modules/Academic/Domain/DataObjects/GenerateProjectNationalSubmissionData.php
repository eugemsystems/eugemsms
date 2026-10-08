<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class GenerateProjectNationalSubmissionData
{
    public function __construct(
        public int $instrumentId,
        public int $academicYearId,
        public int $generatedByUserId,
    ) {}
}

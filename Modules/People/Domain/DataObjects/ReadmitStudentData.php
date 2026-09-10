<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class ReadmitStudentData
{
    public function __construct(
        public int $studentId,
        public int $academicYearId,
        public int $termId,
        public int $readmittedByUserId,
    ) {}
}

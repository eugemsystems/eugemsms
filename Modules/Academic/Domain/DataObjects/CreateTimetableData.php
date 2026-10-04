<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateTimetableData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $structureId,
        public string $name,
        public int $createdByUserId,
    ) {}
}

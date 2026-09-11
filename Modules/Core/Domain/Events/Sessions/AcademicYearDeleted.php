<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Sessions;

final class AcademicYearDeleted
{
    public function __construct(
        public readonly int $academicYearId,
        public readonly int $schoolId,
    ) {}
}

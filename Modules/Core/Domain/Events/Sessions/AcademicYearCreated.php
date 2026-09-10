<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Sessions;

use Modules\Core\Models\AcademicYear;

final class AcademicYearCreated
{
    public function __construct(
        public readonly AcademicYear $academicYear,
    ) {}
}

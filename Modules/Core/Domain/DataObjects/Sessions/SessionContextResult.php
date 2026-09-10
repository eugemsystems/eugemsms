<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

use Modules\Core\Models\AcademicYear;
use Modules\Core\Models\Term;

final readonly class SessionContextResult
{
    public function __construct(
        public AcademicYear $academicYear,
        public ?Term $term,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

final readonly class DeleteTermData
{
    public function __construct(
        public int $termId,
        public int $schoolId,
        public int $academicYearId,
    ) {}
}

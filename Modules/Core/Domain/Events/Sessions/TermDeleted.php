<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Sessions;

final class TermDeleted
{
    public function __construct(
        public readonly int $termId,
        public readonly int $schoolId,
        public readonly int $academicYearId,
    ) {}
}

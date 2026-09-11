<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

final readonly class DeleteAcademicYearData
{
    public function __construct(
        public int $yearId,
        public int $schoolId,
    ) {}
}

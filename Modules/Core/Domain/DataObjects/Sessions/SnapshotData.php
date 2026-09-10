<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Sessions;

final readonly class SnapshotData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public string $snapshotType,
        public ?int $takenByUserId = null,
    ) {}
}

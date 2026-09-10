<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class AllocateClassData
{
    public function __construct(
        public int $studentId,
        public int $classId,
        public int $termId,
        public int $allocatedByUserId,
        public CarbonInterface $effectiveFrom,
        public string $allocationType = 'initial',
        public ?string $notes = null,
    ) {}
}

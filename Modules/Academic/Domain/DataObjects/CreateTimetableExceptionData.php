<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateTimetableExceptionData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public CarbonInterface $exceptionDate,
        public string $exceptionType,
        public string $affectedScope,
        public string $reason,
        public int $createdByUserId,
        public ?int $scopeId = null,
        public ?int $alternativeStructureId = null,
        public bool $suppressesAttendance = false,
    ) {}
}

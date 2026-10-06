<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class GenerateReportCardsData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $requestedByUserId,
        public ?int $classId = null,
        public ?int $gradeLevelId = null,
        public ?int $templateId = null,
    ) {}
}

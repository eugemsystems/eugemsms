<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateCurriculumFrameworkData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $authority,
        public CarbonInterface $effectiveFrom,
        public string $continuousAssessmentModel,
        public int $createdByUserId,
        public ?string $referenceCircular = null,
        public ?string $notes = null,
    ) {}
}

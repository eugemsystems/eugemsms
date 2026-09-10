<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class LinkGuardianToStudentData
{
    public function __construct(
        public int $studentId,
        public int $guardianId,
        public string $relationship,
        public int $createdByUserId,
        public bool $isPrimaryContact = false,
        public bool $isEmergencyContact = false,
        public bool $isFeeResponsible = false,
        public bool $mayCollectLearner = false,
        public bool $mayViewFullBalance = false,
        public bool $hasCourtRestriction = false,
        public ?CarbonInterface $effectiveFrom = null,
    ) {}
}

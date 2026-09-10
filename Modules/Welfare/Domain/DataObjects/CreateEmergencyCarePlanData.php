<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

final readonly class CreateEmergencyCarePlanData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $title,
        public string $triggerSigns,
        public string $immediateActions,
        public string $whoToCall,
        public ?int $conditionId = null,
        public ?string $medicationLocation = null,
        public ?string $medicationName = null,
        public ?string $doNotDo = null,
        public ?string $reviewDueOn = null,
    ) {}
}

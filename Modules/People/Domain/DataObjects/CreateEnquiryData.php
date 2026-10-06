<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateEnquiryData
{
    public function __construct(
        public int $schoolId,
        public string $source,
        public string $enquirerName,
        public ?string $enquirerPhone = null,
        public ?string $enquirerEmail = null,
        public ?string $learnerName = null,
        public ?CarbonInterface $learnerDob = null,
        public ?int $interestedGradeLevelId = null,
        public ?string $interestedResidency = null,
        public ?string $message = null,
        public ?int $intakeId = null,
        public ?int $assignedTo = null,
        public ?CarbonInterface $nextFollowUpOn = null,
    ) {}
}

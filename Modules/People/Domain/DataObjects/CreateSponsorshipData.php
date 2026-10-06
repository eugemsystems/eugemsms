<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateSponsorshipData
{
    public function __construct(
        public int $schoolId,
        public int $guardianId,
        public string $name,
        public string $sponsorshipType,
        public CarbonInterface $startsOn,
        public int $createdByUserId,
        public ?int $budgetMinor = null,
        public ?string $budgetCurrency = null,
        public ?int $maxBeneficiaries = null,
        public ?CarbonInterface $endsOn = null,
        public ?string $contactPerson = null,
        public ?string $reportingFrequency = null,
    ) {}
}

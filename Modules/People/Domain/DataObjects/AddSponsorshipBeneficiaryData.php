<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class AddSponsorshipBeneficiaryData
{
    public function __construct(
        public int $sponsorshipId,
        public int $studentId,
        public int $createdByUserId,
        public int $commitmentMinor = 0,
        public ?CarbonInterface $startsOn = null,
        public ?string $performanceCondition = null,
    ) {}
}

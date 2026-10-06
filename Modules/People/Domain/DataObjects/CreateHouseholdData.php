<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class CreateHouseholdData
{
    public function __construct(
        public int $schoolId,
        public string $name,
        public ?int $headGuardianId = null,
        public ?string $addressLine1 = null,
        public ?string $city = null,
        public bool $combinedStatement = true,
        public bool $siblingDiscountEligible = true,
    ) {}
}

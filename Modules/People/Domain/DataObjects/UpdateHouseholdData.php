<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class UpdateHouseholdData
{
    public function __construct(
        public int $householdId,
        public string $name,
        public ?int $headGuardianId,
        public bool $combinedStatement,
        public bool $siblingDiscountEligible,
        public ?string $addressLine1 = null,
        public ?string $city = null,
    ) {}
}

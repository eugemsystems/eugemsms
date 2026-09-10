<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

/**
 * All fields except `schoolId`/`actingUserId` are optional partial-update
 * fields — `null` means "leave unchanged", never "clear the value" (use an
 * empty string for nullable text fields that should be cleared). `code`
 * is deliberately absent: BR-CORE-02-001 makes it immutable after
 * creation. `baseCurrency` is present but BR-CORE-02-010-gated: rejected
 * once the school has any recorded financial transaction.
 */
final readonly class UpdateSchoolData
{
    public function __construct(
        public int $schoolId,
        public ?int $actingUserId = null,
        public ?string $name = null,
        public ?string $baseCurrency = null,
        public ?string $shortName = null,
        public ?string $centreNumber = null,
        public ?string $emisCode = null,
        public ?string $category = null,
        public ?string $responsibleAuthority = null,
        public ?string $band = null,
        public ?string $province = null,
        public ?string $district = null,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $city = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $website = null,
        public ?string $motto = null,
        public ?string $logoPath = null,
        public ?string $crestPath = null,
        public ?string $letterheadPath = null,
        public ?string $primaryColour = null,
        public ?string $secondaryColour = null,
        public ?string $timezone = null,
        public ?string $locale = null,
        public ?int $headUserId = null,
    ) {}
}

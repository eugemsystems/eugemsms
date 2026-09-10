<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class CreateSchoolData
{
    public function __construct(
        public int $tenantId,
        public string $code,
        public string $name,
        public string $category,
        public ?int $actingUserId = null,
        public ?string $shortName = null,
        public ?string $centreNumber = null,
        public ?string $emisCode = null,
        public ?string $responsibleAuthority = null,
        public ?string $band = null,
        public ?string $province = null,
        public ?string $district = null,
        public string $baseCurrency = 'USD',
        public string $timezone = 'Africa/Harare',
        public string $locale = 'en_ZW',
    ) {}
}

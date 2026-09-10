<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class CreateTenantData
{
    public function __construct(
        public string $name,
        public string $slug,
        public string $type = 'independent',
        public ?string $contactName = null,
        public ?string $contactEmail = null,
        public ?string $contactPhone = null,
        public string $country = 'ZW',
        public bool $isGroupReportingEnabled = false,
    ) {}
}

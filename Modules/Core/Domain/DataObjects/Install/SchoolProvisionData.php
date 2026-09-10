<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

final readonly class SchoolProvisionData
{
    public function __construct(
        public int $tenantId,
        public string $name,
        public string $code,
        public string $baseCurrency,
        public string $timezone,
        public string $locale,
        public int $adminUserId,
    ) {}
}

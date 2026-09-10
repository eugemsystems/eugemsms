<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Settings;

final readonly class ExportConfigurationProfileData
{
    public function __construct(
        public int $tenantId,
        public int $sourceSchoolId,
        public string $name,
        public ?int $actingUserId = null,
        public ?string $description = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Settings;

final readonly class ImportConfigurationProfileData
{
    public function __construct(
        public int $profileId,
        public int $targetSchoolId,
        public ?int $actingUserId = null,
        public bool $overwriteSettings = false,
        public bool $overwriteCustomFields = false,
    ) {}
}

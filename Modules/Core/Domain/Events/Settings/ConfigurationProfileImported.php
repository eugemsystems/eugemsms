<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Settings;

final class ConfigurationProfileImported
{
    public function __construct(
        public readonly int $profileId,
        public readonly int $targetSchoolId,
    ) {}
}

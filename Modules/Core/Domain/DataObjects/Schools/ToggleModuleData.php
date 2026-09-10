<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

final readonly class ToggleModuleData
{
    public function __construct(
        public int $schoolId,
        public string $moduleCode,
        public bool $enable,
        public int $actingUserId,
        public ?string $expiresAt = null,
    ) {}
}

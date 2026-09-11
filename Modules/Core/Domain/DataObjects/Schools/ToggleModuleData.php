<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Schools;

/**
 * `actingUserId` is null for a system-driven toggle — Book J SAA-01's
 * subscription engine syncing `school_modules` from a plan change, with
 * no human admin in the loop.
 */
final readonly class ToggleModuleData
{
    public function __construct(
        public int $schoolId,
        public string $moduleCode,
        public bool $enable,
        public ?int $actingUserId = null,
        public ?string $expiresAt = null,
    ) {}
}

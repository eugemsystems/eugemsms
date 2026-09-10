<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

final readonly class MigrationResult
{
    /**
     * @param  array<int, string>  $ranMigrations
     */
    public function __construct(
        public bool $successful,
        public array $ranMigrations,
        public ?string $failedMigration = null,
        public ?string $errorMessage = null,
    ) {}
}

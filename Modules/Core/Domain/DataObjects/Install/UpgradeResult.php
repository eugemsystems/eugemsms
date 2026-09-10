<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Install;

use Modules\Core\Domain\Support\Install\UpgradeStatus;

final readonly class UpgradeResult
{
    /**
     * @param  array<int, string>  $ranMigrations
     */
    public function __construct(
        public UpgradeStatus $status,
        public ?string $backupReference,
        public array $ranMigrations,
        public ?string $message = null,
    ) {}
}

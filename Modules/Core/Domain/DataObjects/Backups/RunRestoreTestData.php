<?php

declare(strict_types=1);

namespace Modules\Core\Domain\DataObjects\Backups;

final readonly class RunRestoreTestData
{
    public function __construct(
        public int $backupId,
        public string $targetEnvironment = 'isolated',
    ) {}
}

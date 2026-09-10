<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Install;

use Modules\Core\Domain\Contracts\Install\BackupProvider;
use Modules\Core\Domain\Contracts\Install\BackupResult;

final class NullBackupProvider implements BackupProvider
{
    public function backup(): BackupResult
    {
        return new BackupResult(
            verified: false,
            message: 'No backup provider is configured yet (ships with CORE-13).',
        );
    }
}

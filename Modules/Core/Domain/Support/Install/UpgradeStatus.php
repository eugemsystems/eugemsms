<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Install;

enum UpgradeStatus: string
{
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';
}

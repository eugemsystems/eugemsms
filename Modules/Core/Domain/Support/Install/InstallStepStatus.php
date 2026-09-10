<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support\Install;

enum InstallStepStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
}

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Support;

/**
 * Book A CORE-03 §2.
 */
enum RolloverStatus: string
{
    case Pending = 'pending';
    case Validating = 'validating';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case RolledBack = 'rolled_back';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed, self::RolledBack], true);
    }
}

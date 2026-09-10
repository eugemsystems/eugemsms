<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Support;

/**
 * Book B FIN-04 §4/BR-FIN-04-015. Recorded on every allocation it
 * produces — `Manual` needs the caller's own ordering supplied
 * alongside it.
 */
enum AllocationStrategy: string
{
    case OldestFirst = 'auto_oldest';
    case ComponentPriority = 'auto_priority';
    case Manual = 'manual';
}

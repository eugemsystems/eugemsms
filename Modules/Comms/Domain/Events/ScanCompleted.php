<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\ScanRun;

final class ScanCompleted
{
    public function __construct(
        public readonly ScanRun $scanRun,
    ) {}
}

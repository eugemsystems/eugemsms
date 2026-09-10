<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\StaffWorkload;

final class WorkloadExceeded
{
    public function __construct(
        public readonly StaffWorkload $workload,
    ) {}
}

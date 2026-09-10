<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\TeacherAllocation;

final class TeacherAllocationEnded
{
    public function __construct(
        public readonly TeacherAllocation $allocation,
    ) {}
}

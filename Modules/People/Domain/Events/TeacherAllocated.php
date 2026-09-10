<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\TeacherAllocation;

/**
 * Consumed by `ACA-02` and `ACA-03` once they exist — neither listens
 * yet.
 */
final class TeacherAllocated
{
    public function __construct(
        public readonly TeacherAllocation $allocation,
    ) {}
}

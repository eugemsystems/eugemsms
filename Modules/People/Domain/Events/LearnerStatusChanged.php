<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\Student;

final class LearnerStatusChanged
{
    public function __construct(
        public readonly Student $student,
        public readonly string $fromStatus,
        public readonly string $toStatus,
    ) {}
}

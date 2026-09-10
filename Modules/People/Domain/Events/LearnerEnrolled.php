<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\Student;

final class LearnerEnrolled
{
    public function __construct(
        public readonly Student $student,
    ) {}
}

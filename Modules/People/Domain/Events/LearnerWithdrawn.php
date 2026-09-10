<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Illuminate\Support\Carbon;
use Modules\People\Models\Student;

final class LearnerWithdrawn
{
    public function __construct(
        public readonly Student $student,
        public readonly Carbon $exitedOn,
    ) {}
}

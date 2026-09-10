<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\DutyAssignment;

final class DutyAssigned
{
    public function __construct(
        public readonly DutyAssignment $assignment,
    ) {}
}

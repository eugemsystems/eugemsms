<?php

declare(strict_types=1);

namespace Modules\People\Domain\Events;

use Modules\People\Models\DutyAssignment;

final class DutySwapped
{
    public function __construct(
        public readonly DutyAssignment $original,
        public readonly DutyAssignment $replacement,
    ) {}
}

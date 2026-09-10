<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Events;

use Modules\Compliance\Models\StatutorySchoolReturn;

final class StatutorySchoolReturnDeadlineDue
{
    public function __construct(
        public readonly StatutorySchoolReturn $return,
        public readonly int $daysUntilDue,
    ) {}
}

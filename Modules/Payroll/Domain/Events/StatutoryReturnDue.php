<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Events;

use Modules\Payroll\Models\StatutoryReturn;

final class StatutoryReturnDue
{
    public function __construct(
        public readonly StatutoryReturn $statutoryReturn,
        public readonly int $daysUntilDue,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Events;

use Modules\Payroll\Models\StatutoryReturn;

final class StatutoryReturnOverdue
{
    public function __construct(
        public readonly StatutoryReturn $statutoryReturn,
    ) {}
}

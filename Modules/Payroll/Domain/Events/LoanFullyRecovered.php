<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Events;

use Modules\Payroll\Models\StaffLoan;

final class LoanFullyRecovered
{
    public function __construct(
        public readonly StaffLoan $loan,
    ) {}
}

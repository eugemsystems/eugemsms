<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Events;

use Modules\Payroll\Models\PayrollRun;

final class PayrollComputed
{
    public function __construct(
        public readonly PayrollRun $payrollRun,
    ) {}
}

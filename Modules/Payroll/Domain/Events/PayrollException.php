<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Events;

final class PayrollException
{
    public function __construct(
        public readonly int $payrollRunId,
        public readonly int $staffId,
        public readonly string $reason,
    ) {}
}

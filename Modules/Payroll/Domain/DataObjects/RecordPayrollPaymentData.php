<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

final readonly class RecordPayrollPaymentData
{
    public function __construct(
        public int $payrollRunId,
        public int $bankFileId,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

final readonly class PostPayrollRunData
{
    public function __construct(
        public int $payrollRunId,
        public PayrollGlAccounts $glAccounts,
        public int $postedByUserId,
    ) {}
}

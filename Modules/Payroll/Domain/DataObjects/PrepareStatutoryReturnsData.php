<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

final readonly class PrepareStatutoryReturnsData
{
    public function __construct(
        public int $payrollRunId,
    ) {}
}

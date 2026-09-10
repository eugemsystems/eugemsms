<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Events;

final class UnconfirmedStatutoryConfigBlocking
{
    public function __construct(
        public readonly int $schoolId,
        public readonly int $payrollRunId,
        public readonly string $message,
    ) {}
}

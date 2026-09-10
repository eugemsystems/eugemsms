<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

final readonly class PrepareItf16ReturnData
{
    public function __construct(
        public int $schoolId,
        public int $taxYear,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class ConvertBankLineToSuspenseData
{
    public function __construct(
        public int $bankStatementLineId,
        public int $academicYearId,
        public int $termId,
        public int $convertedByUserId,
        public int $suspenseAccountId,
    ) {}
}

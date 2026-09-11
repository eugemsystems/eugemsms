<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class ReportLoanLostData
{
    public function __construct(
        public int $loanId,
        public int $feeComponentId,
        public int $chargedByUserId,
    ) {}
}

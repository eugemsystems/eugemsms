<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class ReturnLoanData
{
    public function __construct(
        public int $loanId,
        public int $returnedByUserId,
        public string $conditionAtReturn = 'good',
        public ?int $feeComponentId = null,
        public ?CarbonInterface $returnedOn = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class AssessDamageAndRefundDepositData
{
    public function __construct(
        public int $academicYearId,
        public int $termId,
        public string $currency,
        public int $depositsHeldLiabilityAccountId,
        public int $cashAccountId,
        public int $performedByUserId,
        public int $damageDeductedMinor = 0,
        public ?int $damageRecoveryIncomeAccountId = null,
        public ?string $damageAssessmentNote = null,
        public ?CarbonInterface $refundedAt = null,
    ) {}
}

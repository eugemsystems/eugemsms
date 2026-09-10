<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordHireDepositData
{
    public function __construct(
        public int $academicYearId,
        public int $termId,
        public int $amountMinor,
        public string $currency,
        public int $cashAccountId,
        public int $depositsHeldLiabilityAccountId,
        public int $performedByUserId,
        public ?CarbonInterface $receivedAt = null,
    ) {}
}

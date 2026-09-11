<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordDonationData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public string $donorName,
        public int $amountMinor,
        public string $currency,
        public int $bankAccountId,
        public int $recordedByUserId,
        public ?int $campaignId = null,
        public ?int $pledgeId = null,
        public ?int $bursaryEndowmentId = null,
        public ?int $incomeAccountId = null,
        public bool $isRestricted = false,
        public ?string $restrictionPurpose = null,
        public ?CarbonInterface $receivedAt = null,
    ) {}
}

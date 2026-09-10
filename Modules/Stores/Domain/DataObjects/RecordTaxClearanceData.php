<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordTaxClearanceData
{
    public function __construct(
        public int $schoolId,
        public int $supplierId,
        public string $certificateNumber,
        public CarbonInterface $issuedOn,
        public CarbonInterface $expiresOn,
        public ?int $verifiedByUserId = null,
        public string $verificationMethod = 'manual',
    ) {}
}

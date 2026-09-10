<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class GrantMedicalConsentData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public int $guardianId,
        public string $consentType,
        public bool $granted,
        public CarbonInterface $grantedAt,
        public string $grantedVia,
        public CarbonInterface $effectiveFrom,
        public ?string $scopeDetail = null,
        public ?int $witnessStaffId = null,
        public ?int $documentFileId = null,
        public ?CarbonInterface $effectiveTo = null,
    ) {}
}

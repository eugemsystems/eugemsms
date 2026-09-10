<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class MakeExternalReferralData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $referralType,
        public string $facilityName,
        public string $reason,
        public string $urgency,
        public CarbonInterface $referredAt,
        public int $referredByUserId,
        public ?int $admissionId = null,
        public ?int $incidentId = null,
        public ?string $transportMethod = null,
        public ?int $escortStaffId = null,
        public ?bool $guardianPresent = null,
        public ?string $consentReference = null,
    ) {}
}

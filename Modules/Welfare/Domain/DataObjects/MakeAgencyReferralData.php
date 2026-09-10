<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class MakeAgencyReferralData
{
    public function __construct(
        public int $schoolId,
        public int $caseId,
        public string $agencyType,
        public string $agencyName,
        public CarbonInterface $referredAt,
        public int $referredByUserId,
        public string $reason,
        public string $consentBasis,
        public ?string $contactPerson = null,
        public ?string $informationShared = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RegisterVehicleComplianceData
{
    public function __construct(
        public int $schoolId,
        public int $vehicleId,
        public string $complianceType,
        public CarbonInterface $expiresOn,
        public ?string $referenceNumber = null,
        public ?CarbonInterface $issuedOn = null,
        public ?int $costMinor = null,
        public ?string $currency = null,
        public ?string $issuingAuthority = null,
    ) {}
}

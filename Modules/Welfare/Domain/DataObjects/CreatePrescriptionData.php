<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreatePrescriptionData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $medicationName,
        public string $dose,
        public string $frequency,
        public string $route,
        public string $prescribedBy,
        public CarbonInterface $prescribedOn,
        public CarbonInterface $startsOn,
        public int $guardianConsentId,
        public ?CarbonInterface $endsOn = null,
        public bool $isPrn = false,
        public ?int $maxDosesPerDay = null,
        public ?int $prescriptionFileId = null,
        public bool $isSelfAdministered = false,
        public ?string $storageLocation = null,
    ) {}
}

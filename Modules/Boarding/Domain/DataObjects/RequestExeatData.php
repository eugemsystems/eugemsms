<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RequestExeatData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $studentId,
        public int $exeatTypeId,
        public string $reason,
        public CarbonInterface $departsAt,
        public CarbonInterface $returnsBy,
        public string $destinationAddress,
        public string $destinationProvince,
        public string $contactPhone,
        public string $collectionMethod,
        public string $requestSource,
        public ?int $requestedByGuardianId = null,
        public ?int $requestedByUserId = null,
        public ?int $collectingGuardianId = null,
        public ?string $collectingPersonName = null,
        public ?string $collectingPersonIdNo = null,
        public ?string $collectingPersonPhone = null,
        public ?int $oneOffAuthorisationBy = null,
        public ?string $destinationCity = null,
        public string $destinationCountry = 'ZW',
        public ?int $supportingDocumentId = null,
        public bool $feeArrearsExceedThreshold = false,
        public bool $isSuspended = false,
        public bool $overrideQuota = false,
        public ?string $overrideReason = null,
    ) {}
}

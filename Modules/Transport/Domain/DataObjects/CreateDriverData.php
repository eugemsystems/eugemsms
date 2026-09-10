<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateDriverData
{
    /**
     * @param  array<int, string>  $licenceClasses
     */
    public function __construct(
        public int $schoolId,
        public int $staffId,
        public string $licenceNumber,
        public array $licenceClasses,
        public CarbonInterface $licenceExpiresOn,
        public ?string $defensiveDrivingCert = null,
        public ?CarbonInterface $defensiveExpiresOn = null,
        public ?CarbonInterface $medicalCertificateOn = null,
        public ?CarbonInterface $medicalExpiresOn = null,
        public ?int $yearsExperience = null,
    ) {}
}

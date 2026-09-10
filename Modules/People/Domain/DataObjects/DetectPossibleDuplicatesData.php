<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class DetectPossibleDuplicatesData
{
    public function __construct(
        public int $schoolId,
        public string $firstName,
        public string $lastName,
        public CarbonInterface $dateOfBirth,
        public ?string $nationalRegistrationNo = null,
        public ?string $birthCertificateNo = null,
        public ?int $excludingStudentId = null,
    ) {}
}

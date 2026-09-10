<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateStudentData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public string $firstName,
        public string $lastName,
        public CarbonInterface $dateOfBirth,
        public string $gender,
        public string $enrolmentType,
        public string $residency,
        public int $sectionId,
        public int $gradeLevelId,
        public int $entryCohortYear,
        public int $createdByUserId,
        public ?string $middleNames = null,
        public ?string $preferredName = null,
        public string $nationality = 'ZW',
        public ?string $nationalRegistrationNo = null,
        public ?string $birthCertificateNo = null,
        public ?int $classId = null,
        public ?int $houseId = null,
        public ?string $pathway = null,
        public ?CarbonInterface $enrolledOn = null,
        public bool $skipDuplicateCheck = false,
    ) {}
}

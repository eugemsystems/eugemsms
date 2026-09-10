<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class SubmitApplicationData
{
    /**
     * @param  array<int, array{relationship: string, title?: string|null, first_name?: string|null, last_name?: string|null, organisation_name?: string|null, primary_phone?: string|null, email?: string|null, is_primary_contact?: bool, is_fee_responsible?: bool, existing_guardian_id?: int|null}>  $guardians
     * @param  array<int, int>|null  $requestedSubjectIds
     */
    public function __construct(
        public int $schoolId,
        public int $intakeId,
        public string $firstName,
        public string $lastName,
        public CarbonInterface $dateOfBirth,
        public string $gender,
        public int $requestedGradeLevelId,
        public string $requestedEnrolmentType,
        public string $requestedResidency,
        public array $guardians,
        public int $createdByUserId,
        public ?string $middleNames = null,
        public string $nationality = 'ZW',
        public ?string $nationalRegistrationNo = null,
        public ?string $birthCertificateNo = null,
        public ?string $requestedPathway = null,
        public ?array $requestedSubjectIds = null,
        public bool $hasSiblingAtSchool = false,
        public ?int $siblingStudentId = null,
        public bool $guardianIsAlumnus = false,
        public bool $guardianIsStaff = false,
    ) {}
}

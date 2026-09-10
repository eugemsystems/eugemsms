<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

/**
 * Non-billing attributes only — `enrolment_type`, `residency`,
 * `grade_level_id`, `class_id`, `section_id`, and `pathway` are
 * deliberately absent; those go through `ChangeBillingAttributeData`.
 */
final readonly class UpdateStudentProfileData
{
    public function __construct(
        public int $studentId,
        public int $updatedByUserId,
        public ?string $firstName = null,
        public ?string $middleNames = null,
        public ?string $lastName = null,
        public ?string $preferredName = null,
        public ?string $homeLanguage = null,
        public ?string $religion = null,
        public ?string $addressLine1 = null,
        public ?string $addressLine2 = null,
        public ?string $suburb = null,
        public ?string $city = null,
        public ?string $province = null,
        public ?string $notes = null,
    ) {}
}

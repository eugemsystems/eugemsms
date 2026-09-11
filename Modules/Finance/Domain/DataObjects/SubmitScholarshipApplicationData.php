<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class SubmitScholarshipApplicationData
{
    /**
     * @param  array<int, int>|null  $supportingDocumentIds
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $schemeId,
        public int $studentId,
        public ?int $appliedByGuardianId = null,
        public ?string $householdIncomeBand = null,
        public ?array $supportingDocumentIds = null,
        public ?string $meansAssessmentScore = null,
        public ?string $academicAverageAtApplication = null,
        public ?string $narrative = null,
    ) {}
}

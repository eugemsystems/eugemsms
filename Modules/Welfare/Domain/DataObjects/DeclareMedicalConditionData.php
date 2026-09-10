<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class DeclareMedicalConditionData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $conditionType,
        public string $name,
        public string $severity,
        public string $declaredBy,
        public CarbonInterface $effectiveFrom,
        public ?string $category = null,
        public ?string $publicSummary = null,
        public bool $requiresEmergencyPlan = false,
        public bool $affectsDietary = false,
        public bool $affectsPhysicalActivity = false,
        public bool $affectsAccommodation = false,
        public ?string $accommodationRequirement = null,
        public ?string $diagnosisNotes = null,
        public ?CarbonInterface $diagnosedOn = null,
        public ?string $diagnosedBy = null,
        public ?int $supportingDocumentId = null,
        public ?int $createdByUserId = null,
    ) {}
}

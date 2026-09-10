<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordDisciplinaryCommitteeData
{
    /**
     * @param  array<int, int>  $panelStaffIds
     * @param  array<int, mixed>|null  $evidenceReviewed
     */
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public CarbonInterface $convenedOn,
        public array $panelStaffIds,
        public string $learnerStatement,
        public string $findings,
        public string $decision,
        public int $chairedByUserId,
        public ?bool $guardianPresent = null,
        public ?bool $learnerPresent = null,
        public ?string $guardianStatement = null,
        public ?array $evidenceReviewed = null,
        public ?int $recommendedSanctionId = null,
    ) {}
}

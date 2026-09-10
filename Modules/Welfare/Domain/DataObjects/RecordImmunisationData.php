<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordImmunisationData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $vaccine,
        public string $status,
        public ?int $doseNumber = null,
        public ?CarbonInterface $administeredOn = null,
        public ?string $administeredBy = null,
        public ?string $batchNumber = null,
        public ?CarbonInterface $nextDueOn = null,
        public ?int $certificateFileId = null,
        public ?string $declineReason = null,
    ) {}
}

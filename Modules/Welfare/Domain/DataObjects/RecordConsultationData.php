<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordConsultationData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public string $consultationType,
        public string $presentingComplaint,
        public string $practitionerType,
        public CarbonInterface $consultedAt,
        public ?string $assessment = null,
        public ?string $plan = null,
        public ?int $practitionerStaffId = null,
        public ?string $externalPractitioner = null,
        public ?CarbonInterface $followUpOn = null,
        public ?int $admissionId = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Welfare\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordCounsellingSessionData
{
    public function __construct(
        public int $schoolId,
        public int $studentId,
        public int $counsellorStaffId,
        public CarbonInterface $sessionAt,
        public string $sessionType,
        public bool $riskIndicatorsPresent = false,
        public ?int $durationMinutes = null,
        public ?string $referralSource = null,
        public ?string $presentingTheme = null,
        public ?string $sessionNotes = null,
        public ?CarbonInterface $nextSessionOn = null,
        public bool $attended = true,
    ) {}
}

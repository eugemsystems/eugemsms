<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateZimsecRegistrationData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $examLevel,
        public string $examSeries,
        public string $centreNumber,
        public CarbonInterface $registrationClosesOn,
        public string $currency,
        public ?int $examinationSessionId = null,
        public ?CarbonInterface $registrationOpensOn = null,
    ) {}
}

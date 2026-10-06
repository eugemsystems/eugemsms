<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class GenerateAttendanceRegisterExportData
{
    public function __construct(
        public int $classId,
        public CarbonInterface $from,
        public CarbonInterface $to,
    ) {}
}

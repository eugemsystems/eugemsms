<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class TransferOutStudentData
{
    public function __construct(
        public int $studentId,
        public int $transferredByUserId,
        public CarbonInterface $exitedOn,
        public ?string $destinationSchool = null,
        public ?string $reason = null,
        public ?string $clearanceOverrideReason = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class WithdrawStudentData
{
    public function __construct(
        public int $studentId,
        public CarbonInterface $exitedOn,
        public int $withdrawnByUserId,
        public ?string $reason = null,
    ) {}
}

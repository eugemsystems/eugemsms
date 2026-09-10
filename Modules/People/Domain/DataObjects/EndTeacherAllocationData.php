<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class EndTeacherAllocationData
{
    public function __construct(
        public int $allocationId,
        public CarbonInterface $endsOn,
        public string $status = 'ended',
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class ConfirmBedAllocationData
{
    public function __construct(
        public int $allocationId,
        public int $confirmedByUserId,
    ) {}
}

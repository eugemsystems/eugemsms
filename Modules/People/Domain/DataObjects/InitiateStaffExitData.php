<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class InitiateStaffExitData
{
    public function __construct(
        public int $staffId,
        public int $initiatedByUserId,
    ) {}
}

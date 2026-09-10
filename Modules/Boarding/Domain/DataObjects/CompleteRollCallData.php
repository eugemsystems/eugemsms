<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class CompleteRollCallData
{
    public function __construct(
        public int $rollCallId,
        public int $completedByUserId,
    ) {}
}

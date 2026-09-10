<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class GenerateTimetableData
{
    public function __construct(
        public int $timetableId,
        public int $requestedByUserId,
    ) {}
}

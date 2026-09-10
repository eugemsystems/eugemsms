<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class CreateHostelBedData
{
    public function __construct(
        public int $schoolId,
        public int $roomId,
        public string $bedNumber,
        public string $bedType,
    ) {}
}

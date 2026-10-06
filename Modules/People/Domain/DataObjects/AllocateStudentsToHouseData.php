<?php

declare(strict_types=1);

namespace Modules\People\Domain\DataObjects;

final readonly class AllocateStudentsToHouseData
{
    /**
     * @param  array<int, int>  $studentIds
     */
    public function __construct(
        public array $studentIds,
        public int $houseId,
        public int $allocatedByUserId,
    ) {}
}

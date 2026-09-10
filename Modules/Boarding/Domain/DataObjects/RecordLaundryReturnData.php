<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordLaundryReturnData
{
    /**
     * @param  array<int, array{studentId: int, itemsBack: int, missingDescription: string|null}>  $items
     */
    public function __construct(
        public int $laundryCycleId,
        public CarbonInterface $returnedAt,
        public array $items,
    ) {}
}

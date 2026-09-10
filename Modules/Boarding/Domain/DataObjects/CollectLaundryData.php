<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CollectLaundryData
{
    /**
     * @param  array<int, array{studentId: int, itemsOut: int}>  $items
     */
    public function __construct(
        public int $laundryCycleId,
        public CarbonInterface $collectedAt,
        public array $items,
    ) {}
}

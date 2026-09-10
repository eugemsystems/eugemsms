<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateStockTakeData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $storeId,
        public string $takeType,
        public CarbonInterface $scheduledFor,
        public ?bool $isBlindCount = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CompleteLibraryStockTakeData
{
    public function __construct(
        public int $stockTakeId,
        public int $feeComponentId,
        public int $chargedByUserId,
    ) {}
}

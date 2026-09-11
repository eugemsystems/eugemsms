<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateLibraryCopyData
{
    public function __construct(
        public int $itemId,
        public int $allocatedByUserId,
        public string $condition = 'new',
        public ?string $barcode = null,
        public ?CarbonInterface $acquiredOn = null,
    ) {}
}

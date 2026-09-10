<?php

declare(strict_types=1);

namespace Modules\Utilities\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class StopGeneratorRunData
{
    public function __construct(
        public int $academicYearId,
        public CarbonInterface $stoppedAt,
        public ?float $dieselLitres = null,
        public ?int $dieselUnitPriceMinor = null,
        public ?string $currency = null,
        public ?int $storeId = null,
        public ?int $itemId = null,
        public ?int $operatedByUserId = null,
    ) {}
}

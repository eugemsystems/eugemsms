<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class AddStaffPayComponentData
{
    public function __construct(
        public int $schoolId,
        public int $payStructureId,
        public int $componentId,
        public string $currency,
        public CarbonInterface $effectiveFrom,
        public ?int $amountMinor = null,
        public ?string $percent = null,
        public ?string $quantity = null,
        public ?string $notes = null,
    ) {}
}

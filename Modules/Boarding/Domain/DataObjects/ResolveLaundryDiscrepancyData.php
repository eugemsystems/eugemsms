<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class ResolveLaundryDiscrepancyData
{
    public function __construct(
        public int $laundryItemId,
        public string $resolution,
        public ?int $feeComponentId = null,
        public ?int $chargeAmountMinor = null,
        public ?int $approvedByUserId = null,
    ) {}
}

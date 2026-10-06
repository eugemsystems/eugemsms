<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateSupplierContractData
{
    public function __construct(
        public int $schoolId,
        public int $supplierId,
        public string $contractNumber,
        public string $title,
        public string $contractType,
        public CarbonInterface $startsOn,
        public ?CarbonInterface $endsOn = null,
        public ?int $valueMinor = null,
        public ?string $currency = null,
        public ?int $renewalNoticeDays = null,
        public bool $autoRenew = false,
        public ?int $ownerStaffId = null,
    ) {}
}

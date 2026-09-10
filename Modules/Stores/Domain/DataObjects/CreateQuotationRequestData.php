<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class CreateQuotationRequestData
{
    /**
     * @param  array<int, int>  $supplierIds
     */
    public function __construct(
        public int $schoolId,
        public int $requisitionId,
        public array $supplierIds,
        public CarbonInterface $issuedOn,
        public CarbonInterface $closesOn,
        public int $requestedByUserId,
    ) {}
}

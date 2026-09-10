<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RequestStoreRequisitionData
{
    /**
     * @param  array<int, array{itemId: int, quantity: float, unit: string}>  $lines
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $storeId,
        public int $costCentreId,
        public string $purpose,
        public int $requestedByUserId,
        public string $currency,
        public array $lines,
        public ?int $requestingDepartmentId = null,
        public ?CarbonInterface $requiredBy = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
    ) {}
}

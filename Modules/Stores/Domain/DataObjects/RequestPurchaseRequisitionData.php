<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RequestPurchaseRequisitionData
{
    /**
     * @param  array<int, array{itemId: ?int, description: string, quantity: float, unit: string, estimatedUnitMinor: ?int}>  $lines
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $departmentId,
        public int $costCentreId,
        public string $justification,
        public int $requestedByUserId,
        public string $currency,
        public array $lines,
        public string $urgency = 'normal',
        public ?CarbonInterface $requiredBy = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?int $budgetLineId = null,
    ) {}
}

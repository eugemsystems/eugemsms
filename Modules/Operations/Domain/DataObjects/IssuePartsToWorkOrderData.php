<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\DataObjects;

final readonly class IssuePartsToWorkOrderData
{
    /**
     * @param  array<int, array{itemId: int, quantity: float, unit: string, description: string}>  $lines
     */
    public function __construct(
        public int $workOrderId,
        public int $storeId,
        public int $issuedByUserId,
        public array $lines,
    ) {}
}

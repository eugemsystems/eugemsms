<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

/**
 * @property array<int, array{itemId: int, quantity: float}> $items
 */
final readonly class DispatchStockTransferData
{
    /**
     * @param  array<int, array{itemId: int, quantity: float}>  $items
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $fromStoreId,
        public int $toStoreId,
        public string $reason,
        public array $items,
        public int $dispatchedByUserId,
    ) {}
}

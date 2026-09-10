<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordGoodsReceivedNoteData
{
    /**
     * @param  array<int, array{poLineId: int, quantityDelivered: float, quantityAccepted: float, quantityRejected: float, rejectionReason: ?string, batchNumber: ?string, expiryDate: ?CarbonInterface, unitCostMinor: int}>  $lines
     */
    public function __construct(
        public int $schoolId,
        public int $termId,
        public int $purchaseOrderId,
        public CarbonInterface $receivedOn,
        public int $receivedByUserId,
        public int $grnAccrualAccountId,
        public array $lines,
        public ?string $deliveryNoteRef = null,
        public ?int $inspectedByUserId = null,
    ) {}
}

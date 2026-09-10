<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RecordQuotationData
{
    /**
     * @param  array<int, array{description: string, quantity: float, unitPriceMinor: int, requisitionLineId: ?int, leadTimeDays: ?int}>  $lines
     */
    public function __construct(
        public int $schoolId,
        public int $quotationRequestId,
        public int $supplierId,
        public CarbonInterface $receivedOn,
        public int $subtotalMinor,
        public int $taxMinor,
        public int $totalMinor,
        public string $currency,
        public array $lines,
        public ?string $quotationReference = null,
        public ?int $deliveryDays = null,
        public ?int $paymentTermsDays = null,
        public bool $isCompliant = true,
        public ?string $nonComplianceNote = null,
    ) {}
}

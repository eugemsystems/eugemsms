<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class RouteReceiptForFiscalisationData
{
    /**
     * @param  array<int, array{source_identifier: string, description: string, amount_minor: int}>  $lines
     * @param  array<int, string>  $paymentMethods
     * @param  string|null  $simulate  test-control only — forwarded verbatim into the fiscal receipt's `payload['_simulate']`, which `FakeFiscalGatewayDriver` reads to force `'unreachable'`/`'reject'`; `null` (the production default) has no effect.
     */
    public function __construct(
        public int $schoolId,
        public string $sourceType,
        public int $sourceId,
        public string $receiptType,
        public string $currency,
        public string $invoiceNumber,
        public CarbonInterface $receiptDate,
        public array $lines,
        public array $paymentMethods,
        public int $performedByUserId,
        public ?string $buyerName = null,
        public ?string $buyerTin = null,
        public ?string $simulate = null,
    ) {}
}

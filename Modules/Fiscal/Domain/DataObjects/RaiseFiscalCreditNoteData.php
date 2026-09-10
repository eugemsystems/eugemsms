<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\DataObjects;

final readonly class RaiseFiscalCreditNoteData
{
    public function __construct(
        public int $originalFiscalReceiptId,
        public string $creditReason,
    ) {}
}

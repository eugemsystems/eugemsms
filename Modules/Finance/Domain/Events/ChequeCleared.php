<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\ReceiptTender;

final class ChequeCleared
{
    public function __construct(
        public readonly ReceiptTender $tender,
    ) {}
}

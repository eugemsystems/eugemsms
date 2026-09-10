<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Events;

use Modules\Finance\Models\Receipt;

final class ReceiptVoided
{
    public function __construct(
        public readonly Receipt $receipt,
    ) {}
}

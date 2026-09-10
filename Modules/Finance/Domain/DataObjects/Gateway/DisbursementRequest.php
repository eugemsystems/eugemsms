<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects\Gateway;

use Modules\Core\Domain\Support\Money;

final readonly class DisbursementRequest
{
    public function __construct(
        public string $destinationWallet,
        public Money $amount,
        public ?string $reference = null,
    ) {}
}

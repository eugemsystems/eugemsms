<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

use Modules\Core\Domain\Support\Money;

final readonly class OpeningBalanceLineData
{
    public function __construct(
        public int $accountId,
        public string $direction,
        public Money $amount,
        public ?string $subledgerType = null,
        public ?int $subledgerId = null,
    ) {}
}

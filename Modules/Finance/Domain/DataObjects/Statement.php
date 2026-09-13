<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class Statement
{
    /**
     * @param  array<int, StatementLine>  $lines
     */
    public function __construct(
        public int $openingBalanceMinor,
        public array $lines,
        public int $closingBalanceMinor,
        public string $currency,
    ) {}
}

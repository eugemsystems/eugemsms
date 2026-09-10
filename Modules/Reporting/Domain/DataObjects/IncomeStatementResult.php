<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\DataObjects;

final readonly class IncomeStatementResult
{
    /**
     * @param  array<int, array{account_id: int, code: string, name: string, amount_minor: int}>  $lines
     * @param  array<int, array{account_id: int, code: string, name: string, amount_minor: int}>  $reconcilingItems
     */
    public function __construct(
        public array $lines,
        public int $netMinor,
        public array $reconcilingItems = [],
        public int $reconcilingTotalMinor = 0,
    ) {}
}

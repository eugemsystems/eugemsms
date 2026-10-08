<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\DataObjects;

final readonly class TrialBalanceResult
{
    /**
     * @param  array<int, array{account_id: int, code: string, name: string, currency: string, debit_minor: int, credit_minor: int}>  $rows
     * @param  array<int, array{account_id: int, code: string, name: string, currency: string, debit_minor: int, credit_minor: int}>  $reconcilingItems
     */
    public function __construct(
        public array $rows,
        public array $reconcilingItems = [],
    ) {}
}

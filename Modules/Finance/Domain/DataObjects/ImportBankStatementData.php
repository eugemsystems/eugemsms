<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

final readonly class ImportBankStatementData
{
    /**
     * @param  array<int, array{transaction_date: string, description: string, reference?: string|null, debit_minor?: int|null, credit_minor?: int|null, value_date?: string|null}>  $lines
     */
    public function __construct(
        public int $schoolId,
        public int $bankAccountId,
        public string $statementFrom,
        public string $statementTo,
        public int $openingBalanceMinor,
        public int $closingBalanceMinor,
        public string $currency,
        public array $lines,
        public int $importedByUserId,
    ) {}
}

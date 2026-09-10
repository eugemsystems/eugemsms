<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\DataObjects;

/**
 * BR-FIN-01-026: computed per currency and consolidated in base
 * currency. Both are always shown together.
 */
final readonly class TrialBalance
{
    /**
     * @param  array<string, array<int, TrialBalanceLine>>  $linesByCurrency
     * @param  array<string, array{debit_minor: int, credit_minor: int}>  $totalsByCurrency
     * @param  array{currency: string, debit_minor: int, credit_minor: int}  $consolidatedBase
     */
    public function __construct(
        public array $linesByCurrency,
        public array $totalsByCurrency,
        public array $consolidatedBase,
    ) {}

    public function isBalanced(): bool
    {
        foreach ($this->totalsByCurrency as $totals) {
            if ($totals['debit_minor'] !== $totals['credit_minor']) {
                return false;
            }
        }

        return $this->consolidatedBase['debit_minor'] === $this->consolidatedBase['credit_minor'];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-FIN-01-003. A journal's debits and credits didn't agree within one
 * currency.
 */
class UnbalancedJournalException extends DomainException
{
    public static function forCurrency(string $currency, int $debitMinor, int $creditMinor): self
    {
        return new self(
            "Journal does not balance in {$currency}: debits {$debitMinor}, credits {$creditMinor}.",
            ['currency' => $currency, 'debit_minor' => $debitMinor, 'credit_minor' => $creditMinor],
        );
    }

    public function errorCode(): string
    {
        return 'UNBALANCED_JOURNAL';
    }
}

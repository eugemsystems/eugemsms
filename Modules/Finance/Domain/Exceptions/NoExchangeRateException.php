<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-FIN-06-007. No active exchange rate exists for the required pair
 * and date. There is no fallback, no last-known-rate, and no 1:1
 * assumption — guessing a rate is how a school's books become fiction.
 */
class NoExchangeRateException extends DomainException
{
    public static function forPair(string $from, string $to, string $at): self
    {
        return new self("No exchange rate from {$from} to {$to} is active on {$at}.", ['from' => $from, 'to' => $to, 'at' => $at]);
    }

    public function errorCode(): string
    {
        return 'NO_EXCHANGE_RATE';
    }
}

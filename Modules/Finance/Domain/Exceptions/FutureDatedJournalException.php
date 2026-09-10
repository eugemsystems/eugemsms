<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-FIN-01-028. `effective_at` beyond `finance.max_future_dating_days`.
 */
class FutureDatedJournalException extends DomainException
{
    public static function forDate(string $effectiveAt, int $maxFutureDays): self
    {
        return new self(
            "Journal effective_at [{$effectiveAt}] is more than {$maxFutureDays} day(s) in the future.",
            ['effective_at' => $effectiveAt, 'max_future_dating_days' => $maxFutureDays],
        );
    }

    public function errorCode(): string
    {
        return 'FUTURE_DATED_JOURNAL';
    }
}

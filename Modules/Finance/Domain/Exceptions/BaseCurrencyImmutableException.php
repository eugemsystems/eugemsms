<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-FIN-06-001. A school's base currency is immutable once any
 * journal exists.
 */
class BaseCurrencyImmutableException extends DomainException
{
    public static function forSchool(int $schoolId): self
    {
        return new self(
            "School [{$schoolId}] already has journals — its base currency can no longer change.",
            ['school_id' => $schoolId],
        );
    }

    public function errorCode(): string
    {
        return 'BASE_CURRENCY_IMMUTABLE';
    }
}

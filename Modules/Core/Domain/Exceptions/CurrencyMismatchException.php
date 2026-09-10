<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * Thrown by Money when two amounts of different currencies are combined
 * directly instead of through the FX service (BR-GLOBAL-021).
 */
class CurrencyMismatchException extends DomainException
{
    public function errorCode(): string
    {
        return 'CURRENCY_MISMATCH';
    }
}

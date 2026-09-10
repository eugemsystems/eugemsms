<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class PeriodLockedException extends DomainException
{
    public function errorCode(): string
    {
        return 'PERIOD_LOCKED';
    }
}

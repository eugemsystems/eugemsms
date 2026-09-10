<?php

declare(strict_types=1);

namespace Modules\People\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * BR-PPL-01-005. `effective_from` may be backdated within
 * `students.backdate_limit_days`, or when backdating is disabled
 * outright.
 */
class BackdateLimitExceededException extends DomainException
{
    public static function forDays(int $requestedDays, int $limitDays): self
    {
        return new self(
            "effective_from is {$requestedDays} day(s) in the past, beyond the {$limitDays}-day backdate limit.",
            ['requested_days' => $requestedDays, 'limit_days' => $limitDays],
        );
    }

    public static function backdatingDisabled(): self
    {
        return new self(
            'Backdated billing attribute changes are disabled for this school.',
            [],
        );
    }

    public function errorCode(): string
    {
        return 'BACKDATE_LIMIT_EXCEEDED';
    }
}

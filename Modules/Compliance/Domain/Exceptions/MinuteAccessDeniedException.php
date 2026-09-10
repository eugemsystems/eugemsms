<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H3 CMP-04 §3/BR-CMP-04-007. A `restricted`/`confidential`
 * minute is only visible to a named role.
 */
final class MinuteAccessDeniedException extends DomainException
{
    public static function forMinute(int $minuteId): self
    {
        return new self(
            "Governance minute #{$minuteId} is restricted to named roles you don't hold.",
            ['minute_id' => $minuteId],
        );
    }

    public function errorCode(): string
    {
        return 'MINUTE_ACCESS_DENIED';
    }
}

<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book I COM-06 §4/BR-COM-06-008.
 */
final class CalendarFeedTokenInvalidException extends DomainException
{
    public static function forToken(): self
    {
        return new self('This calendar feed link is invalid or has been revoked.', []);
    }

    public function errorCode(): string
    {
        return 'CALENDAR_FEED_TOKEN_INVALID';
    }
}

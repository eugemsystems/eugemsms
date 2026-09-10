<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book I COM-02 §4/BR-COM-02-001. A rule referencing an event no
 * module actually publishes fails validation at save time.
 */
final class InvalidRuleEventException extends DomainException
{
    public static function forEvent(string $eventName): self
    {
        return new self(
            "No module publishes an event named '{$eventName}'.",
            ['event_name' => $eventName],
        );
    }

    public function errorCode(): string
    {
        return 'INVALID_RULE_EVENT';
    }
}

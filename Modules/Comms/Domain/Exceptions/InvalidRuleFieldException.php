<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book I COM-02 §4/BR-COM-02-003 (AC-COM-02-003). A rule author may
 * only reference a field the owning entity has actually exposed for
 * automation.
 */
final class InvalidRuleFieldException extends DomainException
{
    public static function forField(string $entityKey, string $field): self
    {
        return new self(
            "Field '{$field}' is not exposed for automation on entity '{$entityKey}'.",
            ['entity' => $entityKey, 'field' => $field],
        );
    }

    public function errorCode(): string
    {
        return 'INVALID_RULE_FIELD';
    }
}

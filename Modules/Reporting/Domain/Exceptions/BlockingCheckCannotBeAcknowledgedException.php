<?php

declare(strict_types=1);

namespace Modules\Reporting\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H3 FIN-12 §4 ⭐/BR-FIN-12-010 (AC-FIN-12-004). "Blocking
 * failures cannot be overridden by any user" — not a permission
 * check, a fact: `AcknowledgeCloseCheckAction` has no code path that
 * writes a `close_check_acknowledgements` row for a blocking check at
 * all.
 */
final class BlockingCheckCannotBeAcknowledgedException extends DomainException
{
    public static function forCheck(string $checkKey): self
    {
        return new self(
            "Check [{$checkKey}] is blocking and cannot be acknowledged by any user — it must actually pass.",
            ['check_key' => $checkKey],
        );
    }

    public function errorCode(): string
    {
        return 'BLOCKING_CHECK_CANNOT_BE_ACKNOWLEDGED';
    }
}

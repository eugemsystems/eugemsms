<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * An external system (gateway, fiscalisation device, SMS provider) could
 * not be reached or failed. Renders 502 and is always retried
 * (Book A Part 1.6) — it must never block the operation it attaches to
 * (Volume 1 rule #16).
 */
abstract class IntegrationException extends SerpException
{
    public function httpStatus(): int
    {
        return 502;
    }

    public function isRetryable(): bool
    {
        return true;
    }
}

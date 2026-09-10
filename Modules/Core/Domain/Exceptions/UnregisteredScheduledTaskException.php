<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

use RuntimeException;

final class UnregisteredScheduledTaskException extends RuntimeException
{
    public static function forKey(string $key): self
    {
        return new self("No scheduled task is registered for key [{$key}].");
    }
}

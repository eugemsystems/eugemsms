<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

class CopyNotAvailableException extends DomainException
{
    public static function forCopy(int $copyId): self
    {
        return new self(
            "Library copy #{$copyId} is not available to issue.",
            ['copy_id' => $copyId],
        );
    }

    public function errorCode(): string
    {
        return 'LIBRARY_COPY_NOT_AVAILABLE';
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * A business rule was violated. Renders 422 (Book A Part 1.6).
 */
abstract class DomainException extends SerpException
{
    public function httpStatus(): int
    {
        return 422;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * The caller is not permitted to perform the action. Renders 403
 * (Book A Part 1.6).
 */
abstract class AuthorisationException extends SerpException
{
    public function httpStatus(): int
    {
        return 403;
    }
}

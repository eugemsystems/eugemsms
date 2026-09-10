<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * Required school or session context could not be resolved. Renders 400
 * (Book A Part 1.6).
 */
abstract class ContextException extends SerpException
{
    public function httpStatus(): int
    {
        return 400;
    }
}

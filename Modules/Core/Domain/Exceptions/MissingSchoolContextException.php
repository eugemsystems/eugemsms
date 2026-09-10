<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class MissingSchoolContextException extends ContextException
{
    public function errorCode(): string
    {
        return 'SCHOOL_CONTEXT_REQUIRED';
    }
}

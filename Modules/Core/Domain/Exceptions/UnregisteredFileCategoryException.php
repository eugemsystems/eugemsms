<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class UnregisteredFileCategoryException extends DomainException
{
    public function errorCode(): string
    {
        return 'UNREGISTERED_FILE_CATEGORY';
    }
}

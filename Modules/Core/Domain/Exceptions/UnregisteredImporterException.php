<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class UnregisteredImporterException extends DomainException
{
    public function errorCode(): string
    {
        return 'UNREGISTERED_IMPORTER';
    }
}

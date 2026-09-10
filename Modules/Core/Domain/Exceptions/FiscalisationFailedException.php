<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

class FiscalisationFailedException extends IntegrationException
{
    public function errorCode(): string
    {
        return 'FISCALISATION_FAILED';
    }
}

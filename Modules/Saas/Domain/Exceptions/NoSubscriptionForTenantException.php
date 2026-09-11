<?php

declare(strict_types=1);

namespace Modules\Saas\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

final class NoSubscriptionForTenantException extends DomainException
{
    public function errorCode(): string
    {
        return 'NO_SUBSCRIPTION_FOR_TENANT';
    }
}

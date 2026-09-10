<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-10-010/AC-CORE-10-005.
 */
class StorageQuotaExceededException extends DomainException
{
    public function errorCode(): string
    {
        return 'STORAGE_QUOTA_EXCEEDED';
    }
}

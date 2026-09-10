<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-10-005/AC-CORE-10-002 — a scan-pending or infected file was
 * requested by someone other than its uploader.
 */
class FileAccessDeniedException extends AuthorisationException
{
    public function errorCode(): string
    {
        return 'FILE_ACCESS_DENIED';
    }
}

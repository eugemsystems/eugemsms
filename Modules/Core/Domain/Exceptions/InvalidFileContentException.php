<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Exceptions;

/**
 * BR-CORE-10-002/AC-CORE-10-001 — the file's real, content-inspected
 * MIME type is not one this category allows, or the file exceeds the
 * category's size limit.
 */
class InvalidFileContentException extends DomainException
{
    public function errorCode(): string
    {
        return 'INVALID_FILE_CONTENT';
    }
}

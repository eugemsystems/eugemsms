<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Files;

use Modules\Core\Models\File;

/**
 * BR-CORE-10-005 — an infected file: quarantined, the uploader
 * notified, and a security event raised.
 */
final class FileQuarantined
{
    public function __construct(
        public readonly File $file,
    ) {}
}

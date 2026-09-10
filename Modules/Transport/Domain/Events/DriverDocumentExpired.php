<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Events;

use Modules\Transport\Models\Driver;

final class DriverDocumentExpired
{
    public function __construct(
        public readonly Driver $driver,
        public readonly string $documentType,
    ) {}
}

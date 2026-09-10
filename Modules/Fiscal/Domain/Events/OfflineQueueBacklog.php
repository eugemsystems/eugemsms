<?php

declare(strict_types=1);

namespace Modules\Fiscal\Domain\Events;

final class OfflineQueueBacklog
{
    public function __construct(
        public readonly int $schoolId,
        public readonly int $queueDepth,
    ) {}
}

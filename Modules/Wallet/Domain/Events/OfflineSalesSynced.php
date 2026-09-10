<?php

declare(strict_types=1);

namespace Modules\Wallet\Domain\Events;

final class OfflineSalesSynced
{
    public function __construct(
        public readonly int $schoolId,
        public readonly int $syncedCount,
    ) {}
}

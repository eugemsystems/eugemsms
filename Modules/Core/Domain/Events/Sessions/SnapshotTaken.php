<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Events\Sessions;

use Modules\Core\Models\PeriodSnapshot;

final class SnapshotTaken
{
    public function __construct(
        public readonly PeriodSnapshot $snapshot,
    ) {}
}

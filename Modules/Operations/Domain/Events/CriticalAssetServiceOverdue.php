<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Events;

use Modules\Operations\Models\MaintenanceAsset;

final class CriticalAssetServiceOverdue
{
    public function __construct(
        public readonly MaintenanceAsset $asset,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Events;

use Modules\Operations\Models\WorkOrder;

final class PreventiveWorkOrderGenerated
{
    public function __construct(
        public readonly WorkOrder $workOrder,
    ) {}
}

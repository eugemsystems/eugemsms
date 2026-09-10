<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\DataObjects;

use Illuminate\Support\Collection;
use Modules\Operations\Models\WorkOrder;

final readonly class GeneratePreventiveWorkOrdersResult
{
    /**
     * @param  Collection<int, WorkOrder>  $generated
     */
    public function __construct(
        public Collection $generated,
    ) {}
}

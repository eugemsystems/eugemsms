<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Events;

use Modules\Boarding\Models\MealRequisitionLine;

final class StockShortfall
{
    public function __construct(
        public readonly MealRequisitionLine $line,
    ) {}
}

<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Events;

use Modules\Stores\Models\Forecast;

final class ForecastGenerated
{
    public function __construct(
        public readonly Forecast $forecast,
    ) {}
}

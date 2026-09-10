<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Events;

use Modules\Boarding\Models\MealService;

final class ServicePlanned
{
    public function __construct(
        public readonly MealService $service,
    ) {}
}

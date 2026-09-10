<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

use Carbon\CarbonInterface;

final readonly class PlanMealServiceData
{
    public function __construct(
        public int $schoolId,
        public int $termId,
        public CarbonInterface $serviceDate,
        public string $meal,
        public string $currency,
        public ?int $menuDayId = null,
        public int $staffMeals = 0,
        public int $guestMeals = 0,
        public ?int $hostelId = null,
    ) {}
}

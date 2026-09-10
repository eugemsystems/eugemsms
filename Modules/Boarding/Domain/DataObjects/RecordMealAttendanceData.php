<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\DataObjects;

final readonly class RecordMealAttendanceData
{
    public function __construct(
        public int $mealServiceId,
        public int $studentId,
        public bool $attended = true,
        public bool $specialMealServed = false,
        public string $method = 'manual',
    ) {}
}

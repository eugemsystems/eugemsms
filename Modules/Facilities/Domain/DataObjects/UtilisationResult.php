<?php

declare(strict_types=1);

namespace Modules\Facilities\Domain\DataObjects;

final readonly class UtilisationResult
{
    public function __construct(
        public int $resourceId,
        public int $bookingCount,
        public float $bookedHours,
        public int $hireRevenueMinor,
        public string $currency,
    ) {}
}

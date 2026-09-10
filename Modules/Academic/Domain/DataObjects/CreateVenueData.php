<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateVenueData
{
    /**
     * @param  array<int, string>|null  $facilities
     */
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public string $venueType,
        public int $capacity,
        public ?int $examCapacity = null,
        public ?string $building = null,
        public ?string $floor = null,
        public ?array $facilities = null,
    ) {}
}

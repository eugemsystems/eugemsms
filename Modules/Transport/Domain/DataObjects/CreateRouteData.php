<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\DataObjects;

final readonly class CreateRouteData
{
    /**
     * @param  array<int, array{name: string, landmark: ?string, zoneId: ?int, distanceFromSchoolKm: ?float}>  $stops
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $code,
        public string $name,
        public string $direction,
        public int $capacity,
        public int $costCentreId,
        public array $stops,
        public ?int $assignedVehicleId = null,
        public ?int $assignedDriverId = null,
        public ?int $assistantStaffId = null,
    ) {}
}

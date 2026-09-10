<?php

declare(strict_types=1);

namespace Modules\Sport\Domain\DataObjects;

final readonly class ConfirmFixtureData
{
    public function __construct(
        public int $fixtureId,
        public int $confirmedByUserId,
        public ?int $vehicleId = null,
        public ?int $driverId = null,
        public ?int $escortStaffId = null,
        public ?int $resourceId = null,
    ) {}
}

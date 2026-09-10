<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\DataObjects;

use Carbon\CarbonInterface;

/**
 * Also the mid-term zone/route change path (BR-OPS-01-006 ⭐) — a
 * caller re-assigning a student to a different route/zone simply
 * calls this again with a later `effectiveFrom`; the previous active
 * assignment closes automatically. Fee proration itself is a
 * documented `FIN-02` deferral (see `LearnerAssignedToRoute`'s own
 * docblock) — this only handles the administrative side for real.
 */
final readonly class AssignLearnerToRouteData
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $studentId,
        public int $routeId,
        public int $pickupStopId,
        public string $direction,
        public CarbonInterface $effectiveFrom,
        public bool $authorisedByGuardian,
        public ?int $dropoffStopId = null,
        public bool $overrideCapacity = false,
        public ?string $overrideReason = null,
        public ?string $notes = null,
    ) {}
}

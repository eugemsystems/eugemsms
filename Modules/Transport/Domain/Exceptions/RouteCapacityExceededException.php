<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Exceptions;

use Modules\Core\Domain\Exceptions\DomainException;

/**
 * Book H2 OPS-01 §4/BR-OPS-01-007. Assignment beyond capacity is
 * possible, but only with an explicit override reason
 * (`AssignLearnerToRouteData::overrideCapacity`/`overrideReason`) —
 * this fires when neither was supplied.
 */
class RouteCapacityExceededException extends DomainException
{
    public static function forRoute(int $routeId, int $capacity): self
    {
        return new self(
            "Route #{$routeId} is at its capacity of {$capacity} — assignment requires an override with a reason (BR-OPS-01-007).",
            ['route_id' => $routeId, 'capacity' => $capacity],
        );
    }

    public function errorCode(): string
    {
        return 'ROUTE_CAPACITY_EXCEEDED';
    }
}

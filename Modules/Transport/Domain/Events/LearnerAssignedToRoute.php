<?php

declare(strict_types=1);

namespace Modules\Transport\Domain\Events;

use Modules\Transport\Models\LearnerTransport;

/**
 * BR-OPS-01-006 ⭐ — real `FIN-02` `usage_based` billing is a
 * documented, pre-existing Book B deferral
 * (`Modules\Finance\Domain\Exceptions\UnsupportedBillingBasisException`).
 * This event exists so that wiring has something to subscribe to once
 * it's built; nothing in `Modules\Transport` consumes it itself.
 */
final class LearnerAssignedToRoute
{
    public function __construct(
        public readonly LearnerTransport $assignment,
    ) {}
}

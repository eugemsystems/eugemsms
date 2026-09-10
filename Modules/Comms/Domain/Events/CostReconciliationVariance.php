<?php

declare(strict_types=1);

namespace Modules\Comms\Domain\Events;

use Modules\Comms\Models\GatewayCostReconciliation;

/**
 * Book I COM-01 §3/BR-COM-01-012 (AC-COM-01-006). Variance beyond
 * `comms.cost_reconciliation_tolerance_percent` is investigated, not
 * absorbed.
 */
final class CostReconciliationVariance
{
    public function __construct(
        public readonly GatewayCostReconciliation $reconciliation,
    ) {}
}

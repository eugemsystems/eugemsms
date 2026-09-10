<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\DataObjects\BudgetAvailabilityResult;
use Modules\Stores\Models\BudgetLine;

/**
 * ACT-CheckBudgetAvailability (Book H1 FIN-11 §3/§6/BR-FIN-11-009/
 * AC-FIN-11-004). Read-only — a requisition exceeding available
 * budget is never blocked here; the caller (`FIN-08`'s own
 * `RequestPurchaseRequisitionAction`) is what decides to route the
 * result to a higher approval level rather than refuse.
 */
final class CheckBudgetAvailabilityAction extends Action
{
    public function execute(?int $budgetLineId, int $requestedMinor): BudgetAvailabilityResult
    {
        if ($budgetLineId === null) {
            return new BudgetAvailabilityResult('no_budget', null);
        }

        $line = BudgetLine::find($budgetLineId);

        if ($line === null) {
            return new BudgetAvailabilityResult('no_budget', null);
        }

        $result = $requestedMinor <= $line->available_minor ? 'within' : 'exceeds';

        return new BudgetAvailabilityResult($result, $line->available_minor);
    }
}

<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Stores\Domain\Events\BudgetApproved;
use Modules\Stores\Models\Budget;

final class ApproveBudgetAction extends Action
{
    public function execute(int $budgetId, int $approvedByUserId, ?Carbon $boardApprovedOn = null): Budget
    {
        $budget = Budget::findOrFail($budgetId);

        if ($budget->status !== 'under_review') {
            throw new InvalidStateTransitionException(
                "Budget #{$budget->id} must be under review to approve (currently {$budget->status}).",
                ['budget_id' => $budget->id, 'status' => $budget->status],
            );
        }

        if ($budget->prepared_by === $approvedByUserId) {
            throw new InvalidStateTransitionException(
                'The preparer of a budget cannot approve it themselves.',
                ['budget_id' => $budget->id],
            );
        }

        return $this->transaction(function () use ($budget, $approvedByUserId, $boardApprovedOn): Budget {
            $budget->update([
                'status' => 'active',
                'approved_by' => $approvedByUserId,
                'approved_at' => Carbon::now(),
                'board_approved_on' => $boardApprovedOn?->toDateString(),
            ]);

            event(new BudgetApproved($budget));

            return $budget;
        });
    }
}

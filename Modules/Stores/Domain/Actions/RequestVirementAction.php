<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\DataObjects\RequestVirementData;
use Modules\Stores\Models\BudgetLine;
use Modules\Stores\Models\BudgetVirement;

/**
 * ACT-RequestVirement (Book H1 FIN-11 §6/BR-FIN-11-011). A locked
 * line refuses virement in either direction — checked here, before
 * any approval step, not left for the approver to notice.
 */
final class RequestVirementAction extends Action
{
    public function execute(RequestVirementData $data): BudgetVirement
    {
        $fromLine = BudgetLine::findOrFail($data->fromLineId);
        $toLine = BudgetLine::findOrFail($data->toLineId);

        if ($fromLine->is_locked || $toLine->is_locked) {
            throw ValidationException::withMessages([
                'fromLineId' => 'A locked budget line cannot be the source or destination of a virement (BR-FIN-11-011).',
            ]);
        }

        return $this->transaction(fn (): BudgetVirement => BudgetVirement::create([
            'school_id' => $fromLine->school_id,
            'budget_id' => $data->budgetId,
            'from_line_id' => $fromLine->id,
            'to_line_id' => $toLine->id,
            'amount_minor' => $data->amountMinor,
            'currency' => $fromLine->currency,
            'reason' => $data->reason,
            'status' => 'pending',
            'requested_by' => $data->requestedByUserId,
            'effective_from' => $data->effectiveFrom->toDateString(),
        ]));
    }
}

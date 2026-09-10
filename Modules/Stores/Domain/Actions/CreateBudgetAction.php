<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\DataObjects\CreateBudgetData;
use Modules\Stores\Models\Budget;

/**
 * ACT-CreateBudget (Book H1 FIN-11 §6/BR-FIN-11-002). Always version 1
 * — a later revision goes through `ReviseBudgetAction`, which is the
 * only path that ever increments `version`.
 */
final class CreateBudgetAction extends Action
{
    public function execute(CreateBudgetData $data): Budget
    {
        return $this->transaction(fn (): Budget => Budget::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'name' => $data->name,
            'budget_type' => $data->budgetType,
            'period_basis' => $data->periodBasis,
            'currency' => $data->currency,
            'version' => 1,
            'status' => 'draft',
            'total_income_minor' => 0,
            'total_expense_minor' => 0,
            'surplus_minor' => 0,
            'prepared_by' => $data->preparedByUserId,
        ]));
    }
}

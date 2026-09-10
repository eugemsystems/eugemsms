<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\Account;
use Modules\Stores\Models\Budget;

/**
 * ACT-ConsolidateBudget (Book H1 FIN-11 §6/BR-FIN-11-003). Sums every
 * submitted line into the budget's own income/expense/surplus totals
 * — income and expense are told apart by each line's own account
 * type, never by a sign convention a caller could get backwards.
 */
final class ConsolidateBudgetAction extends Action
{
    public function execute(int $budgetId): Budget
    {
        $budget = Budget::with('lines.account.accountType')->findOrFail($budgetId);

        $income = 0;
        $expense = 0;

        foreach ($budget->lines as $line) {
            if ($line->account->accountType->code === 'INCOME') {
                $income += $line->annual_amount_minor;
            } else {
                $expense += $line->annual_amount_minor;
            }
        }

        return $this->transaction(fn (): Budget => tap($budget)->update([
            'total_income_minor' => $income,
            'total_expense_minor' => $expense,
            'surplus_minor' => $income - $expense,
            'status' => 'under_review',
        ]));
    }
}

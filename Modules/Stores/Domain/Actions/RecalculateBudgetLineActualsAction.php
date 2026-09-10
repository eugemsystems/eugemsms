<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Models\Term;
use Modules\Stores\Models\BudgetLine;

/**
 * ACT-RecalculateBudgetLineActuals (Book H1 FIN-11 §6/BR-FIN-11-008 ⭐/
 * AC-FIN-11-005). The ONLY path that ever writes `budget_lines.actual_minor`
 * — always summed fresh from `journal_lines` by account and cost
 * centre (and term, when the line itself is term-scoped), signed by
 * the account's own normal balance. No action anywhere in this
 * module accepts a manually typed actual figure.
 */
final class RecalculateBudgetLineActualsAction extends Action
{
    public function execute(int $budgetLineId): BudgetLine
    {
        $line = BudgetLine::with('account.accountType', 'budget')->findOrFail($budgetLineId);

        $query = DB::table('journal_lines')
            ->where('school_id', $line->school_id)
            ->where('account_id', $line->account_id)
            ->where('cost_centre_id', $line->cost_centre_id);

        if ($line->term_id !== null) {
            $query->where('term_id', $line->term_id);
        } else {
            $termIds = Term::withoutGlobalScopes()->where('academic_year_id', $line->budget->academic_year_id)->pluck('id');
            $query->whereIn('term_id', $termIds);
        }

        $normalBalance = $line->account->accountType->normal_balance;

        $sums = $query->selectRaw(
            'SUM(CASE WHEN direction = ? THEN amount_minor ELSE -amount_minor END) as net',
            [$normalBalance],
        )->first();

        $actual = (int) ($sums->net ?? 0);

        return $this->transaction(function () use ($line, $actual): BudgetLine {
            $line->actual_minor = $actual;
            $line->recomputeAvailable();
            $line->save();

            return $line;
        });
    }
}

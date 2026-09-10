<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Stores\Domain\Events\BudgetRevised;
use Modules\Stores\Models\Budget;
use Modules\Stores\Models\BudgetLine;

/**
 * ACT-ReviseBudget (Book H1 FIN-11 §6/BR-FIN-11-002). Creates a new
 * version rather than mutating the active one — the prior version's
 * own `actual`/`committed` position at the moment it was superseded
 * stays exactly as it was, retrievable forever; only the new version
 * carries a fresh starting line-set forward for editing.
 */
final class ReviseBudgetAction extends Action
{
    public function execute(int $budgetId, int $preparedByUserId): Budget
    {
        $current = Budget::with('lines')->findOrFail($budgetId);

        if (! in_array($current->status, ['active', 'approved'], true)) {
            throw new InvalidStateTransitionException(
                "Budget #{$current->id} must be active to revise (currently {$current->status}).",
                ['budget_id' => $current->id, 'status' => $current->status],
            );
        }

        return $this->transaction(function () use ($current, $preparedByUserId): Budget {
            $revision = Budget::create([
                'school_id' => $current->school_id,
                'academic_year_id' => $current->academic_year_id,
                'name' => $current->name,
                'budget_type' => $current->budget_type,
                'period_basis' => $current->period_basis,
                'currency' => $current->currency,
                'version' => $current->version + 1,
                'status' => 'draft',
                'total_income_minor' => 0,
                'total_expense_minor' => 0,
                'surplus_minor' => 0,
                'prepared_by' => $preparedByUserId,
            ]);

            foreach ($current->lines as $line) {
                BudgetLine::create([
                    'school_id' => $line->school_id,
                    'budget_id' => $revision->id,
                    'account_id' => $line->account_id,
                    'cost_centre_id' => $line->cost_centre_id,
                    'term_id' => $line->term_id,
                    'annual_amount_minor' => $line->annual_amount_minor,
                    'term_1_minor' => $line->term_1_minor,
                    'term_2_minor' => $line->term_2_minor,
                    'term_3_minor' => $line->term_3_minor,
                    'currency' => $line->currency,
                    'committed_minor' => 0,
                    'actual_minor' => 0,
                    'available_minor' => $line->annual_amount_minor,
                    'prior_year_actual_minor' => $line->actual_minor,
                    'basis_note' => $line->basis_note,
                ]);
            }

            $current->update(['status' => 'revised']);

            event(new BudgetRevised($current, $revision));

            return $revision;
        });
    }
}

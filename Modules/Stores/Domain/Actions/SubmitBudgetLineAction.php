<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Core\Domain\Support\Settings\ScopeChain;
use Modules\Core\Domain\Support\Settings\SettingResolver;
use Modules\Stores\Domain\DataObjects\SubmitBudgetLineData;
use Modules\Stores\Models\Budget;
use Modules\Stores\Models\BudgetLine;

/**
 * ACT-SubmitBudgetLine (Book H1 FIN-11 §6/BR-FIN-11-001/003). Bottom-up
 * departmental submission — each call upserts one line, keyed by
 * account/cost-centre/term the same way the DB's own unique
 * constraint does. Only legal against a `draft` budget: once
 * submitted for review, lines stop moving under a department's own
 * editing.
 */
final class SubmitBudgetLineAction extends Action
{
    public function __construct(
        private readonly SettingResolver $settings,
    ) {}

    public function execute(SubmitBudgetLineData $data): BudgetLine
    {
        $budget = Budget::findOrFail($data->budgetId);

        if ($budget->status !== 'draft') {
            throw new InvalidStateTransitionException(
                "Budget #{$budget->id} must be in draft to submit lines (currently {$budget->status}).",
                ['budget_id' => $budget->id, 'status' => $budget->status],
            );
        }

        $requireBasisNote = (bool) $this->settings->get('budget.require_basis_note', new ScopeChain(schoolId: $budget->school_id));

        if ($requireBasisNote && trim((string) $data->basisNote) === '') {
            throw ValidationException::withMessages([
                'basisNote' => 'A basis note is required for this budget line.',
            ]);
        }

        return $this->transaction(function () use ($budget, $data): BudgetLine {
            // firstOrNew, not updateOrCreate: a brand new line must start
            // committed_minor/actual_minor at a real 0 in memory (a column
            // DB-default alone leaves those attributes unset on the
            // in-memory model until the next fetch); re-submitting an
            // EXISTING line must never touch its live committed/actual
            // position, only the plan figures below.
            $line = BudgetLine::firstOrNew([
                'budget_id' => $budget->id,
                'account_id' => $data->accountId,
                'cost_centre_id' => $data->costCentreId,
                'term_id' => $data->termId,
            ]);

            if (! $line->exists) {
                $line->committed_minor = 0;
                $line->actual_minor = 0;
            }

            $line->fill([
                'school_id' => $budget->school_id,
                'annual_amount_minor' => $data->annualAmountMinor,
                'term_1_minor' => $data->term1Minor,
                'term_2_minor' => $data->term2Minor,
                'term_3_minor' => $data->term3Minor,
                'currency' => $budget->currency,
                'prior_year_actual_minor' => $data->priorYearActualMinor,
                'basis_note' => $data->basisNote,
            ]);

            $line->recomputeAvailable();
            $line->save();

            return $line;
        });
    }
}

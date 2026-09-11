<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Finance\Models\SchemeBudgetEnvelope;
use Modules\People\Domain\DataObjects\SyncEndowmentBudgetEnvelopeData;
use Modules\People\Models\BursaryEndowment;
use Modules\People\Models\Donation;

/**
 * ACT-SyncEndowmentBudgetEnvelope (Book K PPL-06 §4/BR-PPL-06-008 ⭐/
 * AC-PPL-06-004). Projects an endowment's available balance —
 * committed capital plus every donation ever accumulated against it —
 * onto `FIN-07`'s own `scheme_budget_envelopes.budget_minor` for the
 * given academic year. This is the ONLY thing this Action does:
 * `FIN-07`'s own `SchemeBudgetEnvelope::wouldExceed()` refusal keeps
 * working completely unchanged once the budget figure feeding it is
 * right — the actual cap enforcement is never duplicated here.
 */
final class SyncEndowmentBudgetEnvelopeAction extends Action
{
    public function execute(SyncEndowmentBudgetEnvelopeData $data): SchemeBudgetEnvelope
    {
        $endowment = BursaryEndowment::findOrFail($data->bursaryEndowmentId);

        $donatedTotal = (int) Donation::query()
            ->where('bursary_endowment_id', $endowment->id)
            ->sum('amount_minor');

        $availableBalance = (int) ($endowment->endowment_capital_minor ?? 0) + $donatedTotal;

        return $this->transaction(function () use ($endowment, $data, $availableBalance): SchemeBudgetEnvelope {
            $envelope = SchemeBudgetEnvelope::firstOrNew([
                'school_id' => $endowment->school_id,
                'scheme_id' => $endowment->funds_scheme_id,
                'academic_year_id' => $data->academicYearId,
            ]);

            $envelope->fill([
                'currency' => $endowment->currency,
                'budget_minor' => $availableBalance,
                'committed_minor' => $envelope->committed_minor ?? 0,
                'utilised_minor' => $envelope->utilised_minor ?? 0,
            ])->save();

            return $envelope->fresh();
        });
    }
}

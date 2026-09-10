<?php

declare(strict_types=1);

namespace Modules\Finance\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Finance\Domain\DataObjects\ReverseFxRevaluationData;
use Modules\Finance\Domain\DataObjects\ReverseJournalData;
use Modules\Finance\Models\FxRevaluation;

/**
 * ACT-ReverseFxRevaluation (Book B FIN-06 §2/BR-FIN-06-012). A
 * revaluation is undone by a full journal reversal — through the same
 * `ReverseJournalAction` every other correction in the ledger uses —
 * never by deleting the `fx_revaluations` row.
 */
final class ReverseFxRevaluationAction extends Action
{
    public function __construct(
        private readonly ReverseJournalAction $reverseJournal,
    ) {}

    public function execute(ReverseFxRevaluationData $data): FxRevaluation
    {
        $revaluation = FxRevaluation::findOrFail($data->revaluationId);

        if ($revaluation->status !== 'posted') {
            throw new InvalidStateTransitionException(
                "Only a posted revaluation can be reversed — this one is {$revaluation->status}.",
                ['revaluation_id' => $revaluation->id],
            );
        }

        if ($revaluation->journal_id === null) {
            throw new InvalidStateTransitionException(
                'This revaluation posted no journal (nothing was revalued) and has nothing to reverse.',
                ['revaluation_id' => $revaluation->id],
            );
        }

        return $this->transaction(function () use ($revaluation, $data): FxRevaluation {
            $this->reverseJournal->execute(new ReverseJournalData(
                journalId: $revaluation->journal_id,
                reason: $data->reason,
                reversedByUserId: $data->reversedByUserId,
            ));

            $revaluation->update(['status' => 'reversed']);

            return $revaluation;
        });
    }
}

<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\Events\CommitmentCreated;
use Modules\Stores\Models\BudgetCommitment;
use Modules\Stores\Models\BudgetLine;

/**
 * ACT-CreateBudgetCommitment (Book H1 FIN-11 §6 ⭐/BR-FIN-11-004/007/
 * AC-FIN-11-001). `available` drops the instant this runs — the real
 * consumer of `FIN-08`'s `PurchaseOrderApproved` event, closing that
 * "fires but nothing listens yet" deferral for good.
 */
final class CreateBudgetCommitmentAction extends Action
{
    public function execute(int $budgetLineId, string $sourceType, int $sourceId, int $committedMinor): BudgetCommitment
    {
        $line = BudgetLine::findOrFail($budgetLineId);

        return $this->transaction(function () use ($line, $sourceType, $sourceId, $committedMinor): BudgetCommitment {
            $commitment = BudgetCommitment::create([
                'school_id' => $line->school_id,
                'budget_line_id' => $line->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'committed_minor' => $committedMinor,
                'released_minor' => 0,
                'outstanding_minor' => $committedMinor,
                'currency' => $line->currency,
                'committed_at' => Carbon::now(),
                'status' => 'open',
            ]);

            $line->committed_minor += $committedMinor;
            $line->recomputeAvailable();
            $line->save();

            event(new CommitmentCreated($commitment));

            return $commitment;
        });
    }
}

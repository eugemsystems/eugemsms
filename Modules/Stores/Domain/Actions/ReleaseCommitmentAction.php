<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\Stores\Domain\Events\CommitmentReleased;
use Modules\Stores\Models\BudgetCommitment;

/**
 * ACT-ReleaseCommitment (Book H1 FIN-11 §6 ⭐/BR-FIN-11-005/006/
 * AC-FIN-11-002/003). Called both when an invoice matches (releasing
 * proportionally, `actual` picked up separately via
 * `RecalculateBudgetLineActualsAction` reading the real journal an
 * invoice approval posts) and when an order closes short (releasing
 * whatever is left outstanding in one call). `available` is
 * unaffected by a proportional release exactly as AC-FIN-11-002
 * describes — committed drops and actual rises by the same amount,
 * so the sum committed+actual, and therefore available, holds steady.
 */
final class ReleaseCommitmentAction extends Action
{
    public function __construct(
        private readonly RecalculateBudgetLineActualsAction $recalculateActuals,
    ) {}

    public function execute(string $sourceType, int $sourceId, ?int $releasedMinor = null): ?BudgetCommitment
    {
        $commitment = BudgetCommitment::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereIn('status', ['open', 'partially_released'])
            ->first();

        if ($commitment === null) {
            return null;
        }

        $amount = min($releasedMinor ?? $commitment->outstanding_minor, $commitment->outstanding_minor);

        if ($amount <= 0) {
            return $commitment;
        }

        return $this->transaction(function () use ($commitment, $amount): BudgetCommitment {
            $newOutstanding = $commitment->outstanding_minor - $amount;

            $commitment->update([
                'released_minor' => $commitment->released_minor + $amount,
                'outstanding_minor' => $newOutstanding,
                'released_at' => Carbon::now(),
                'status' => $newOutstanding <= 0 ? 'released' : 'partially_released',
            ]);

            $line = $commitment->budgetLine;
            $line->committed_minor = max(0, $line->committed_minor - $amount);
            $line->save();

            $this->recalculateActuals->execute($line->id);

            event(new CommitmentReleased($commitment, $amount));

            return $commitment;
        });
    }
}

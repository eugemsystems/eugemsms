<?php

declare(strict_types=1);

namespace Modules\Stores\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Stores\Domain\Events\VirementApproved;
use Modules\Stores\Models\BudgetVirement;

/**
 * ACT-ApproveVirement (Book H1 FIN-11 §6/BR-FIN-11-010/011). Both
 * lines' own `annual_amount_minor` move by the same amount in
 * opposite directions — that movement IS "both lines' history shows
 * the transfer" (BR-FIN-11-010), since each line's own
 * `annual_amount_minor` before/after is retrievable from this very
 * virement row.
 */
final class ApproveVirementAction extends Action
{
    public function execute(int $virementId, int $approvedByUserId): BudgetVirement
    {
        $virement = BudgetVirement::with('fromLine', 'toLine')->findOrFail($virementId);

        if ($virement->status !== 'pending') {
            throw new InvalidStateTransitionException(
                "Virement #{$virement->id} must be pending to approve (currently {$virement->status}).",
                ['virement_id' => $virement->id, 'status' => $virement->status],
            );
        }

        if ($virement->fromLine->is_locked || $virement->toLine->is_locked) {
            throw ValidationException::withMessages([
                'virementId' => 'A locked budget line cannot be the source or destination of a virement (BR-FIN-11-011).',
            ]);
        }

        if ($virement->requested_by === $approvedByUserId) {
            throw new InvalidStateTransitionException(
                'The requester of a virement cannot approve it themselves.',
                ['virement_id' => $virement->id],
            );
        }

        return $this->transaction(function () use ($virement, $approvedByUserId): BudgetVirement {
            $fromLine = $virement->fromLine;
            $toLine = $virement->toLine;

            $fromLine->annual_amount_minor -= $virement->amount_minor;
            $fromLine->recomputeAvailable();
            $fromLine->save();

            $toLine->annual_amount_minor += $virement->amount_minor;
            $toLine->recomputeAvailable();
            $toLine->save();

            $virement->update(['status' => 'approved', 'approved_by' => $approvedByUserId]);

            event(new VirementApproved($virement));

            return $virement;
        });
    }
}

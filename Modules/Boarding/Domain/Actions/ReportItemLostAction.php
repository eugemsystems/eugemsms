<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Models\LearnerIssuedItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ReportItemLost (Book F BRD-05 §3/BR-BRD-05-004). Reporting never
 * charges anything by itself — `ApproveLostItemChargeAction` is the
 * only path to a replacement-cost `FIN-02` charge.
 */
final class ReportItemLostAction extends Action
{
    public function execute(int $learnerIssuedItemId, ?string $notes = null): LearnerIssuedItem
    {
        $issuedItem = LearnerIssuedItem::findOrFail($learnerIssuedItemId);

        if ($issuedItem->status !== 'issued') {
            throw new InvalidStateTransitionException(
                "Item #{$issuedItem->id} must be issued to be reported lost (currently {$issuedItem->status}).",
                ['learner_issued_item_id' => $issuedItem->id, 'status' => $issuedItem->status],
            );
        }

        return $this->transaction(fn (): LearnerIssuedItem => tap($issuedItem)->update([
            'status' => 'lost',
            'notes' => $notes ?? $issuedItem->notes,
        ]));
    }
}

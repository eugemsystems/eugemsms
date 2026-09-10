<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Modules\Compliance\Domain\DataObjects\ReviewDisposalQueueItemData;
use Modules\Compliance\Models\DisposalQueueItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;

/**
 * ACT-ReviewDisposalQueueItem (Book H3 CMP-03 §3/BR-CMP-03-006 ⭐). A
 * human decides `approved` (clears it for
 * `DisposeQueuedRecordAction`) or `deferred` (pushed to a later
 * review date with a reason) — the review this rule exists to force.
 */
final class ReviewDisposalQueueItemAction extends Action
{
    private const array VALID_DECISIONS = ['approved', 'deferred'];

    public function execute(ReviewDisposalQueueItemData $data): DisposalQueueItem
    {
        if (! in_array($data->decision, self::VALID_DECISIONS, true)) {
            throw new InvalidStateTransitionException(
                "Disposal queue review decision must be 'approved' or 'deferred', got '{$data->decision}'.",
                ['decision' => $data->decision],
            );
        }

        return $this->transaction(function () use ($data): DisposalQueueItem {
            $item = DisposalQueueItem::findOrFail($data->itemId);

            $item->update([
                'review_status' => $data->decision,
                'reviewed_by' => $data->reviewedByUserId,
                'deferred_until' => $data->decision === 'deferred' ? $data->deferredUntil : null,
                'deferral_reason' => $data->decision === 'deferred' ? $data->deferralReason : null,
            ]);

            return $item;
        });
    }
}

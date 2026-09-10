<?php

declare(strict_types=1);

namespace Modules\Compliance\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Compliance\Models\DisposalQueueItem;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\People\Models\Application;

/**
 * ACT-DisposeQueuedRecord (Book H3 CMP-03 §3/BR-CMP-03-006 ⭐). Only
 * ever acts on an `approved` queue item — never `pending_review` or
 * `deferred`. Only the one concrete record type this pass wires
 * (`application_unsuccessful`, see `EnqueueDueDisposalsAction`'s own
 * scope note) is actually disposable; every other `record_type`
 * refuses with a clear reason rather than silently no-op-ing.
 */
final class DisposeQueuedRecordAction extends Action
{
    public function execute(int $itemId): DisposalQueueItem
    {
        return $this->transaction(function () use ($itemId): DisposalQueueItem {
            $item = DisposalQueueItem::findOrFail($itemId);

            if ($item->review_status !== 'approved') {
                throw new InvalidStateTransitionException(
                    "Disposal queue item #{$item->id} cannot be disposed: review_status is '{$item->review_status}', not 'approved'.",
                    ['item_id' => $item->id, 'review_status' => $item->review_status],
                );
            }

            $method = $item->schedule->disposal_method;

            match ($item->record_type) {
                'application_unsuccessful' => $this->disposeApplication($item->record_id, $method),
                default => throw new InvalidStateTransitionException(
                    "No disposal handler is wired for record_type '{$item->record_type}' in this pass.",
                    ['record_type' => $item->record_type],
                ),
            };

            $item->update(['review_status' => 'disposed', 'disposed_at' => Carbon::now(), 'disposal_method' => $method]);

            return $item;
        });
    }

    private function disposeApplication(int $applicationId, string $method): void
    {
        $application = Application::find($applicationId);

        if ($application === null) {
            return;
        }

        match ($method) {
            'delete' => $application->delete(),
            'anonymise' => $application->update([
                'first_name' => 'Redacted', 'middle_names' => null, 'last_name' => 'Redacted',
                'national_registration_no' => null, 'birth_certificate_no' => null,
            ]),
            default => null,
        };
    }
}

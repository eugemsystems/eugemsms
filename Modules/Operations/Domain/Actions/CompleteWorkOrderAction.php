<?php

declare(strict_types=1);

namespace Modules\Operations\Domain\Actions;

use Illuminate\Validation\ValidationException;
use Modules\Core\Domain\Actions\Action;
use Modules\Core\Domain\Exceptions\InvalidStateTransitionException;
use Modules\Operations\Domain\Events\WorkOrderCompleted;
use Modules\Operations\Models\WorkOrder;

/**
 * ACT-CompleteWorkOrder (Book H2 OPS-02 §6/BR-OPS-02-011). Completion
 * notes are always required; a work order whose fault report (or
 * itself, for a standalone safety-relevant job) affects safety also
 * requires at least one completion photo.
 */
final class CompleteWorkOrderAction extends Action
{
    /**
     * @param  array<int, int>|null  $completionPhotoIds
     */
    public function execute(int $workOrderId, string $completionNotes, ?array $completionPhotoIds, int $completedByUserId): WorkOrder
    {
        $workOrder = WorkOrder::with('faultReport')->findOrFail($workOrderId);

        if (! in_array($workOrder->status, ['approved', 'in_progress'], true)) {
            throw new InvalidStateTransitionException(
                "Work order #{$workOrder->id} must be approved or in progress to complete (currently {$workOrder->status}).",
                ['work_order_id' => $workOrder->id, 'status' => $workOrder->status],
            );
        }

        if (trim($completionNotes) === '') {
            throw ValidationException::withMessages([
                'completionNotes' => 'Completion notes are required (BR-OPS-02-011).',
            ]);
        }

        if ($workOrder->faultReport?->affects_safety && ($completionPhotoIds === null || $completionPhotoIds === [])) {
            throw ValidationException::withMessages([
                'completionPhotoIds' => 'A completion photograph is required for safety-affecting work (BR-OPS-02-011).',
            ]);
        }

        return $this->transaction(function () use ($workOrder, $completionNotes, $completionPhotoIds): WorkOrder {
            $workOrder->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completion_notes' => $completionNotes,
                'completion_photo_ids' => $completionPhotoIds,
            ]);

            event(new WorkOrderCompleted($workOrder));

            return $workOrder;
        });
    }
}

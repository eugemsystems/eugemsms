<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\UpdateActionItemStatusData;
use Modules\Academic\Models\DepartmentMeeting;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-UpdateActionItemStatus (Book K ACA-11 §4/BR-ACA-11-008/
 * AC-ACA-11-005). Updates exactly ONE action item's `status` without
 * touching the rest of the minutes or any other action item.
 */
final class UpdateActionItemStatusAction extends Action
{
    public function execute(UpdateActionItemStatusData $data): DepartmentMeeting
    {
        $meeting = DepartmentMeeting::findOrFail($data->meetingId);
        $items = $meeting->action_items ?? [];

        if (! array_key_exists($data->actionItemIndex, $items)) {
            throw new InvalidArgumentException("Meeting #{$meeting->id} has no action item at index {$data->actionItemIndex}.");
        }

        $items[$data->actionItemIndex]['status'] = $data->status;

        return $this->transaction(function () use ($meeting, $items): DepartmentMeeting {
            $meeting->update(['action_items' => array_values($items)]);

            return $meeting->fresh();
        });
    }
}

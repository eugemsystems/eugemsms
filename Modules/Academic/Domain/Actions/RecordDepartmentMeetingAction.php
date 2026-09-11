<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\RecordDepartmentMeetingData;
use Modules\Academic\Models\DepartmentMeeting;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-RecordDepartmentMeeting (Book K ACA-11 §4/BR-ACA-11-008). Each
 * action item is stored with an explicit `status` (defaulting to
 * `open`) so it's independently trackable from the moment the
 * minutes are recorded.
 */
final class RecordDepartmentMeetingAction extends Action
{
    public function execute(RecordDepartmentMeetingData $data): DepartmentMeeting
    {
        $actionItems = $data->actionItems === null ? null : array_map(
            fn (array $item): array => [...$item, 'status' => $item['status'] ?? 'open'],
            $data->actionItems,
        );

        return $this->transaction(fn (): DepartmentMeeting => DepartmentMeeting::create([
            'school_id' => $data->schoolId,
            'department_id' => $data->departmentId,
            'meeting_date' => $data->meetingDate->toDateString(),
            'attendee_staff_ids' => $data->attendeeStaffIds,
            'agenda' => $data->agenda,
            'minutes' => $data->minutes,
            'action_items' => $actionItems,
            'chaired_by' => $data->chairedByStaffId,
        ]));
    }
}

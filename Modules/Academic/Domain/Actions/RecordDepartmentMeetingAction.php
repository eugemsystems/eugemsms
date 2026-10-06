<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use InvalidArgumentException;
use Modules\Academic\Domain\DataObjects\RecordDepartmentMeetingData;
use Modules\Academic\Models\DepartmentMeeting;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Models\Department;
use Modules\People\Models\Staff;

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
        if (trim($data->minutes) === '' || $data->attendeeStaffIds === []) {
            throw new InvalidArgumentException('Meeting minutes and at least one attendee are required.');
        }

        $attendees = array_values(array_unique($data->attendeeStaffIds));
        $owners = array_map(fn (array $item): int => (int) ($item['owner'] ?? 0), $data->actionItems ?? []);

        if (! Department::query()->whereKey($data->departmentId)->exists()
            || Staff::query()->whereIn('id', array_values(array_unique([...$attendees, $data->chairedByStaffId, ...$owners])))->count() !== count(array_unique([...$attendees, $data->chairedByStaffId, ...$owners]))) {
            throw new InvalidArgumentException('The department, attendees, chair and action owners must all belong to this school.');
        }

        foreach ($data->actionItems ?? [] as $item) {
            if (trim((string) ($item['action'] ?? '')) === '' || strtotime((string) ($item['due_date'] ?? '')) === false) {
                throw new InvalidArgumentException('Every action item needs a description, an owner and a due date.');
            }
        }

        $actionItems = $data->actionItems === null ? null : array_map(
            fn (array $item): array => [...$item, 'status' => $item['status'] ?? 'open'],
            $data->actionItems,
        );

        return $this->transaction(fn (): DepartmentMeeting => DepartmentMeeting::create([
            'school_id' => $data->schoolId,
            'department_id' => $data->departmentId,
            'meeting_date' => $data->meetingDate->toDateString(),
            'attendee_staff_ids' => $attendees,
            'agenda' => $data->agenda,
            'minutes' => $data->minutes,
            'action_items' => $actionItems,
            'chaired_by' => $data->chairedByStaffId,
        ]));
    }
}

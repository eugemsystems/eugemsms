<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Illuminate\Support\Carbon;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\InitiateStaffExitData;
use Modules\People\Models\Staff;
use Modules\People\Models\StaffExitChecklist;

/**
 * ACT-InitiateStaffExit (Book C PPL-04 §4/BR-PPL-04-021). Seeds the
 * clearance checklist's default items — this only starts the exit
 * process; the staff member isn't marked `exited` until
 * `ProcessStaffExitAction` runs on the actual exit date, which can
 * be well after this.
 */
final class InitiateStaffExitAction extends Action
{
    public function execute(InitiateStaffExitData $data): StaffExitChecklist
    {
        $staff = Staff::findOrFail($data->staffId);

        return $this->transaction(fn (): StaffExitChecklist => StaffExitChecklist::create([
            'school_id' => $staff->school_id,
            'staff_id' => $staff->id,
            'initiated_at' => Carbon::now(),
            'initiated_by' => $data->initiatedByUserId,
            'items' => array_map(
                fn (array $item): array => [...$item, 'is_cleared' => false, 'cleared_by' => null, 'cleared_at' => null],
                StaffExitChecklist::DEFAULT_ITEMS,
            ),
        ]));
    }
}

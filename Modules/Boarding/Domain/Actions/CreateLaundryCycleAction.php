<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CreateLaundryCycleData;
use Modules\Boarding\Models\LaundryCycle;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateLaundryCycle (Book F BRD-05 §2/§4).
 */
final class CreateLaundryCycleAction extends Action
{
    public function execute(CreateLaundryCycleData $data): LaundryCycle
    {
        return $this->transaction(fn (): LaundryCycle => LaundryCycle::create([
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'hostel_id' => $data->hostelId,
            'cycle_date' => $data->cycleDate->toDateString(),
            'items_collected' => 0,
            'items_returned' => 0,
            'items_missing' => 0,
            'status' => 'scheduled',
            'supervised_by' => $data->supervisedByStaffId,
        ]));
    }
}

<?php

declare(strict_types=1);

namespace Modules\Boarding\Domain\Actions;

use Modules\Boarding\Domain\DataObjects\CreateMenuCycleData;
use Modules\Boarding\Models\MenuCycle;
use Modules\Core\Domain\Actions\Action;

/**
 * ACT-CreateMenuCycle (Book F BRD-04 §2).
 */
final class CreateMenuCycleAction extends Action
{
    public function execute(CreateMenuCycleData $data): MenuCycle
    {
        return $this->transaction(fn (): MenuCycle => MenuCycle::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'name' => $data->name,
            'cycle_length_days' => $data->cycleLengthDays,
            'starts_on' => $data->startsOn,
            'ends_on' => $data->endsOn,
            'is_active' => true,
        ]));
    }
}

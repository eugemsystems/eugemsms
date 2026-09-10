<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateDutyRosterData;
use Modules\People\Models\DutyRoster;

final class CreateDutyRosterAction extends Action
{
    public function execute(CreateDutyRosterData $data): DutyRoster
    {
        return $this->transaction(fn (): DutyRoster => DutyRoster::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'term_id' => $data->termId,
            'duty_type' => $data->dutyType,
            'name' => $data->name,
            'rotation_pattern' => $data->rotationPattern,
            'eligible_categories' => $data->eligibleCategories,
            'is_active' => true,
        ]));
    }
}

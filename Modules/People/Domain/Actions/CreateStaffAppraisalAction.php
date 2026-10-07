<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use InvalidArgumentException;
use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateStaffAppraisalData;
use Modules\People\Models\StaffAppraisal;
use Modules\People\Models\StaffAppraisalRubric;

final class CreateStaffAppraisalAction extends Action
{
    public function execute(CreateStaffAppraisalData $data): StaffAppraisal
    {
        if ($data->rubricId !== null && StaffAppraisalRubric::query()->find($data->rubricId) === null) {
            throw new InvalidArgumentException('Choose one of this school\'s own appraisal rubrics.');
        }

        return $this->transaction(fn (): StaffAppraisal => StaffAppraisal::create([
            'school_id' => $data->schoolId,
            'staff_id' => $data->staffId,
            'academic_year_id' => $data->academicYearId,
            'cycle' => $data->cycle,
            'appraiser_staff_id' => $data->appraiserStaffId,
            'rubric_id' => $data->rubricId,
            'status' => 'draft',
        ]));
    }
}

<?php

declare(strict_types=1);

namespace Modules\People\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\People\Domain\DataObjects\CreateStaffAppraisalData;
use Modules\People\Models\StaffAppraisal;

final class CreateStaffAppraisalAction extends Action
{
    public function execute(CreateStaffAppraisalData $data): StaffAppraisal
    {
        return $this->transaction(fn (): StaffAppraisal => StaffAppraisal::create([
            'school_id' => $data->schoolId,
            'staff_id' => $data->staffId,
            'academic_year_id' => $data->academicYearId,
            'cycle' => $data->cycle,
            'appraiser_staff_id' => $data->appraiserStaffId,
            'status' => 'draft',
        ]));
    }
}

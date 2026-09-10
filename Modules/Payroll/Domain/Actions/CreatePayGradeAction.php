<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Payroll\Domain\DataObjects\CreatePayGradeData;
use Modules\Payroll\Models\PayGrade;

final class CreatePayGradeAction extends Action
{
    public function execute(CreatePayGradeData $data): PayGrade
    {
        return $this->transaction(fn (): PayGrade => PayGrade::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'category' => $data->category,
            'min_salary_minor' => $data->minSalaryMinor,
            'max_salary_minor' => $data->maxSalaryMinor,
            'currency' => $data->currency,
            'nec_grade_reference' => $data->necGradeReference,
            'is_active' => true,
        ]));
    }
}

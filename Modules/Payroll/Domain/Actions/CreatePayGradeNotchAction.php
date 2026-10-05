<?php

declare(strict_types=1);

namespace Modules\Payroll\Domain\Actions;

use Modules\Core\Domain\Actions\Action;
use Modules\Payroll\Domain\DataObjects\CreatePayGradeNotchData;
use Modules\Payroll\Models\PayGradeNotch;

/**
 * ACT-CreatePayGradeNotch (Book H3 PPL-05 §2). A small, narrow,
 * create-only gap-filling Action found during this module's admin-UI
 * pass: `pay_grade_notches` had a real migration/model/factory but no
 * Action anywhere in the domain layer ever created a row (verified by
 * grep — every existing row came from `PayGradeNotchFactory` called
 * directly, the same "model exists, no Action ever wrote one" gap
 * every prior book's admin-UI pass has hit at least once). Mirrors
 * `CreatePayGradeAction`'s own plain-create shape exactly.
 */
final class CreatePayGradeNotchAction extends Action
{
    public function execute(CreatePayGradeNotchData $data): PayGradeNotch
    {
        return $this->transaction(fn (): PayGradeNotch => PayGradeNotch::create([
            'school_id' => $data->schoolId,
            'grade_id' => $data->gradeId,
            'notch' => $data->notch,
            'basic_salary_minor' => $data->basicSalaryMinor,
            'currency' => $data->currency,
            'effective_from' => $data->effectiveFrom->toDateString(),
        ]));
    }
}

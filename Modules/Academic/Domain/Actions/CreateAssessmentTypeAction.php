<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateAssessmentTypeData;
use Modules\Academic\Models\AssessmentType;
use Modules\Core\Domain\Actions\Action;

final class CreateAssessmentTypeAction extends Action
{
    public function execute(CreateAssessmentTypeData $data): AssessmentType
    {
        return $this->transaction(fn (): AssessmentType => AssessmentType::create([
            'school_id' => $data->schoolId,
            'code' => $data->code,
            'name' => $data->name,
            'category' => $data->category,
            'default_weight_percent' => $data->defaultWeightPercent,
            'appears_on_report_card' => $data->appearsOnReportCard,
            'is_examination' => $data->isExamination,
            'sort_order' => $data->sortOrder,
        ]));
    }
}

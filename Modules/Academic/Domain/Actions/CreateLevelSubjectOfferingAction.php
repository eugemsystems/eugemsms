<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateLevelSubjectOfferingData;
use Modules\Academic\Models\LevelSubjectOffering;
use Modules\Core\Domain\Actions\Action;

final class CreateLevelSubjectOfferingAction extends Action
{
    public function execute(CreateLevelSubjectOfferingData $data): LevelSubjectOffering
    {
        return $this->transaction(fn (): LevelSubjectOffering => LevelSubjectOffering::create([
            'school_id' => $data->schoolId,
            'academic_year_id' => $data->academicYearId,
            'grade_level_id' => $data->gradeLevelId,
            'subject_id' => $data->subjectId,
            'pathway_id' => $data->pathwayId,
            'is_compulsory' => $data->isCompulsory,
            'is_available' => $data->isAvailable,
            'periods_per_week' => $data->periodsPerWeek,
            'max_learners' => $data->maxLearners,
            'option_block' => $data->optionBlock,
        ]));
    }
}

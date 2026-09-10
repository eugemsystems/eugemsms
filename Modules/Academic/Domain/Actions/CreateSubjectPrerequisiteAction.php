<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateSubjectPrerequisiteData;
use Modules\Academic\Models\SubjectPrerequisite;
use Modules\Core\Domain\Actions\Action;

final class CreateSubjectPrerequisiteAction extends Action
{
    public function execute(CreateSubjectPrerequisiteData $data): SubjectPrerequisite
    {
        return $this->transaction(fn (): SubjectPrerequisite => SubjectPrerequisite::create([
            'school_id' => $data->schoolId,
            'subject_id' => $data->subjectId,
            'prerequisite_subject_id' => $data->prerequisiteSubjectId,
            'minimum_grade' => $data->minimumGrade,
            'examination' => $data->examination,
            'severity' => $data->severity,
        ]));
    }
}

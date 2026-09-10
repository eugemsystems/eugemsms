<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\Actions;

use Modules\Academic\Domain\DataObjects\CreateSyllabusData;
use Modules\Academic\Models\Syllabus;
use Modules\Core\Domain\Actions\Action;

final class CreateSyllabusAction extends Action
{
    public function execute(CreateSyllabusData $data): Syllabus
    {
        return $this->transaction(fn (): Syllabus => Syllabus::create([
            'school_id' => $data->schoolId,
            'subject_id' => $data->subjectId,
            'grade_level_id' => $data->gradeLevelId,
            'framework_id' => $data->frameworkId,
            'title' => $data->title,
            'version' => $data->version,
            'effective_from' => $data->effectiveFrom?->toDateString(),
            'effective_to' => $data->effectiveTo?->toDateString(),
            'file_id' => $data->fileId,
            'topics' => $data->topics,
            'is_active' => true,
        ]));
    }
}

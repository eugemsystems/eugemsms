<?php

declare(strict_types=1);

namespace Modules\Academic\Domain\DataObjects;

final readonly class CreateSchemeOfWorkData
{
    /**
     * @param  array<int, array<string, mixed>>  $plannedTopics  [{week, topic, objectives, resources}]
     */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $termId,
        public int $subjectId,
        public int $gradeLevelId,
        public int $teacherStaffId,
        public array $plannedTopics,
        public ?int $documentFileId = null,
    ) {}
}
